<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CouponUser;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderStop;
use App\Models\PaymentMethod;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OrderController extends Controller
{

    //
    public function index(Request $request)
    {

        //
        $driverId = $request->driver_id;
        $vendorId = $request->vendor_id;
        $status = $request->status;
        $type = $request->type;
        $vendorTypeId = $request->vendor_type_id;


        $orders = Order::fullData()
            ->when(!empty($vendorId), function ($query) use ($vendorId) {
                return $query->orWhere('vendor_id', $vendorId);
            }, function ($query) {
                return $query->where('user_id', Auth::id());
            })
            ->when(!empty($driverId), function ($query) use ($driverId) {
                return $query->orWhere('driver_id', $driverId);
            })
            ->when(!empty($status), function ($query) use ($status) {
                // return $query->where('status', $status);
                return $query->currentStatus($status);
            })
            ->when($type == "history", function ($query) {
                // return $query->whereIn('status', ['failed', 'cancelled', 'delivered']);
                return $query->currentStatus(['failed', 'cancelled', 'delivered']);
            })
            ->when($type == "assigned", function ($query) {
                // return $query->whereNotIn('status', ['failed', 'cancelled', 'delivered']);
                return $query->otherCurrentStatus(['failed', 'cancelled', 'delivered']);
            })
            ->when($vendorTypeId, function ($query) use ($vendorTypeId) {
                return $query->whereHas("vendor", function ($query) use ($vendorTypeId) {
                    return $query->where('vendor_type_id', $vendorTypeId);
                });
            })
            ->orderBy('created_at', 'DESC')->paginate();
        return $orders;
    }

    public function store(Request $request)
    {


        //if the new order if for packages
        if ($request->type == "package" || $request->type == "parcel") {
            return $this->processPackageDeliveryOrder($request);
        }

        //regular order
        //validate request
        $validator = Validator::make($request->all(), [
            'vendor_id' => 'required|exists:vendors,id',
            'delivery_address_id' => 'sometimes|nullable|exists:delivery_addresses,id',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'sub_total' => 'required|numeric',
            'discount' => 'required|numeric',
            'delivery_fee' => 'required|numeric',
            'tax' => 'required|numeric',
            'total' => 'required|numeric',
        ]);

        if ($validator->fails()) {

            return response()->json([
                "message" => $this->readalbeError($validator),
            ], 400);
        }


        //
        try {

            //check wallet balance if wallet is selected before going further
            $paymentMethod = PaymentMethod::find($request->payment_method_id);
            //wallet check 
            if ($paymentMethod->is_cash && $paymentMethod->slug == "wallet") {

                $wallet = Wallet::mine()->first();
                if (empty($wallet) || $wallet->balance < $request->total) {
                    throw new \Exception(__("Wallet Balance is less than order total amount"), 1);
                }
            }




            //
            DB::beginTransaction();
            $order = new order();
            $order->note = $request->note ?? '';
            $order->vendor_id = $request->vendor_id;
            $order->delivery_address_id = $request->delivery_address_id;
            $order->payment_method_id = $request->payment_method_id;
            $order->sub_total = $request->sub_total;
            $order->discount = $request->discount;
            $order->delivery_fee = $request->delivery_fee;
            $order->tip = $request->tip ?? 0.00;
            $order->tax = $request->tax;
            $order->total = $request->total;
            $order->pickup_date = $request->pickup_date;
            $order->pickup_time = $request->pickup_time;
            $order->payment_status = "pending";
            $order->save();
            $order->setStatus($this->getNewOrderStatus($request));

            //save the coupon used
            $coupon = Coupon::where("code", $request->coupon_code)->first();
            if (!empty($coupon)) {
                $couponUser = new CouponUser();
                $couponUser->coupon_id = $coupon->id;
                $couponUser->user_id = \Auth::id();
                $couponUser->order_id = $order->id;
                $couponUser->save();
            }


            //products
            foreach ($request->products ?? [] as $product) {

                $orderProduct = new OrderProduct();
                $orderProduct->order_id = $order->id;
                $orderProduct->quantity = $product['selected_qty'];
                $orderProduct->price = $product['price'];
                $orderProduct->product_id = $product['product']['id'];
                $orderProduct->options = $product['options_flatten'];
                $orderProduct->save();

                //reduce product qty
                $product = $orderProduct->product;
                if (!empty($product->available_qty)) {
                    $product->available_qty = $product->available_qty - $orderProduct->quantity;
                    $product->save();
                }
            }

            // photo for prescription
            if ($request->hasFile("photo")) {
                $order->clearMediaCollection();
                $order->addMedia($request->photo->getRealPath())->toMediaCollection();
            }

            //
            if ($request->type == "pharmacy" && $request->hasFile("photo")) {
                $order->payment_status = "review";
                $order->save();
            }


            //
            $paymentMethod = PaymentMethod::find($request->payment_method_id);
            $paymentLink = "";
            $message = "";

            if ($paymentMethod->is_cash) {

                //wallet check 
                if ($paymentMethod->slug == "wallet") {
                    //
                    $wallet = Wallet::mine()->first();
                    if (empty($wallet) || $wallet->balance < $request->total) {
                        throw new \Exception(__("Wallet Balance is less than order total amount"), 1);
                    } else {
                        //
                        $wallet->balance -= $request->total;
                        $wallet->save();

                        //RECORD WALLET TRANSACTION
                        $this->recordWalletDebit($wallet, $request->total);
                    }
                }

                $order->payment_status = "successful";
                $order->save();
                $message = __("Order placed successfully. Relax while the vendor process your order");
            } else {
                $message = __("Order placed successfully. Please follow the link to complete payment.");
                if ($order->payment_status == "pending") {
                    $paymentLink = route('order.payment', ["code" => $order->code]);
                }
            }

            //
            DB::commit();

            return response()->json([
                "message" => $message,
                "link" => $paymentLink,
            ], 200);
        } catch (\Exception $ex) {
            \Log::info([
                "Error" => $ex->getMessage(),
                "File" => $ex->getFile(),
                "Line" => $ex->getLine(),
            ]);
            DB::rollback();
            return response()->json([
                "message" => $ex->getMessage()
            ], 400);
        }
    }


    ///handle package order
    public function processPackageDeliveryOrder($request)
    {

        //validate request
        $validator = Validator::make($request->all(), [
            'package_type_id' => 'required|exists:package_types,id',
            'vendor_id' => 'required|exists:vendors,id',
            'pickup_location_id' => 'sometimes|exists:delivery_addresses,id',
            'dropoff_location_id' => 'sometimes|exists:delivery_addresses,id',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'weight' => 'sometimes|nullable|numeric',
            'width' => 'sometimes|nullable|numeric',
            'length' => 'sometimes|nullable|numeric',
            'height' => 'sometimes|nullable|numeric',
            'sub_total' => 'required|numeric',
            'discount' => 'required|numeric',
            'delivery_fee' => 'required|numeric',
            'tax' => 'required|numeric',
            'total' => 'required|numeric',
        ]);

        if ($validator->fails()) {

            return response()->json([
                "message" => $this->readalbeError($validator),
            ], 400);
        }


        //saving to database
        try {

            DB::beginTransaction();
            $order = new order();
            //DON'T TRANSLATE
            $order->vendor_id = $request->vendor_id;
            $order->payment_method_id = $request->payment_method_id;
            $order->note = $request->note ?? '';
            //
            $order->package_type_id = $request->package_type_id;
            $order->pickup_date = $request->pickup_date;
            $order->pickup_time = $request->pickup_time;
            // TODO take extra infos
            $order->weight = $request->weight ?? 0;
            $order->width = $request->width ?? 0;
            $order->length = $request->length ?? 0;
            $order->height = $request->height ?? 0;

            $order->sub_total = $request->sub_total;
            $order->discount = $request->discount;
            $order->delivery_fee = $request->delivery_fee;
            $order->tax = $request->tax;
            $order->total = $request->total;
            $order->save();
            $order->setStatus($this->getNewOrderStatus($request));

            // allow old apps to still place order [Will be removed in future update]
            if (!empty($request->pickup_location_id)) {
                $orderStop = new OrderStop();
                $orderStop->order_id = $order->id;
                $orderStop->stop_id = $request->pickup_location_id;
                $orderStop->save();
            }

            //stops
            if (!empty($request->stops)) {
                foreach ($request->stops as $stop) {

                    $orderStop = new OrderStop();
                    $orderStop->order_id = $order->id;
                    $orderStop->stop_id = $stop['stop_id'] ?? $stop['id'];
                    $orderStop->price = $stop['price'] ?? 0.00;
                    if (!empty($stop["name"])) {
                        $orderStop->name = $stop['name'] ?? '';
                        $orderStop->phone = $stop['phone'] ?? '';
                        $orderStop->note = $stop['note'] ?? '';
                    }

                    $orderStop->save();
                }
            }

            // allow old apps to still place order [Will be removed in future update]
            if (!empty($request->dropoff_location_id)) {
                $orderStop = new OrderStop();
                $orderStop->order_id = $order->id;
                $orderStop->stop_id = $request->dropoff_location_id;
                $orderStop->name = $request->recipient_name;
                $orderStop->phone = $request->recipient_phone;
                $orderStop->note = $request->note ?? '';
                $orderStop->save();
            }


            //
            $paymentMethod = PaymentMethod::find($request->payment_method_id);
            $paymentLink = "";
            $message = "";

            if ($paymentMethod->is_cash) {

                //wallet check 
                if ($paymentMethod->slug == "wallet") {
                    //
                    $wallet = Wallet::mine()->first();
                    if (empty($wallet) || $wallet->balance < $request->total) {
                        throw new \Exception(__("Wallet Balance is less than order total amount"), 1);
                    } else {
                        //
                        $wallet->balance -= $request->total;
                        $wallet->save();

                        //RECORD WALLET TRANSACTION
                        $this->recordWalletDebit($wallet, $request->total);
                    }
                }

                $order->payment_status = "successful";
                $message = __("Order placed successfully. Relax while the vendor process your order");
            } else {
                $message = __("Order placed successfully. Please follow the link to complete payment.");
                $paymentLink = route('order.payment', ["code" => $order->code]);
            }

            //
            $order->save();

            //
            DB::commit();

            return response()->json([
                "message" => $message,
                "link" => $paymentLink,
            ], 200);
        } catch (\Exception $ex) {
            \Log::info([
                "Error" => $ex->getMessage(),
                "Line" => $ex->getLine(),
            ]);
            DB::rollback();
            return response()->json([
                "message" => $ex->getMessage()
            ], 400);
        }
    }

    public function show(Request $request, $id)
    {
        //
        return Order::fullData()->where('id', $id)->first();
        $user = User::find(Auth::id());
        if (!$user->hasAnyRole('client')) {
            return Order::fullData()->where('id', $id)->first();
        } else {
            return Order::fullData()->where('user_id', Auth::id())->where('id', $id)->first();
        }
    }



    //
    public function update(Request $request, $id)
    {
        //
        $user = User::find(Auth::id());
        $order = Order::find($id);
        $enableDriverWallet = (bool) setting('enableDriverWallet', "0");
        $driverWalletRequired = (bool) setting('driverWalletRequired', "0");

        if ($user->hasAnyRole('client') && $user->id != $order->user_id && !in_array($request->status, ['pending', 'cancelled'])) {
            return response()->json([
                "message" => "Order doesn't belong to you"
            ], 400);
        }
        //wallet system
        else if ($request->status == "enroute" && !empty($request->driver_id) && $enableDriverWallet) {

            //
            $userWallet = $user->wallet;
            if (empty($userWallet)) {
                $userWallet = $user->updateWallet(0);
            }

            //allow if wallet has enough balance
            if ($driverWalletRequired) {
                if ($order->total > $userWallet->balance) {
                    return response()->json([
                        "message" => __("Order not assigned. Insufficient wallet balance")
                    ], 400);
                }
            } else if ($order->payment_method->slug == "cash" && $order->total > $userWallet->balance) {
                return response()->json([
                    "message" => __("Insufficient wallet balance, Wallet balance is less than order total amount")
                ], 400);
            } else if ($order->payment_method->slug != "cash" && $order->delivery_fee > $userWallet->balance) {
                return response()->json([
                    "message" => __("Insufficient wallet balance, Wallet balance is less than order delivery fee")
                ], 400);
            }
        }


        //
        try {

            //fetch order
            DB::beginTransaction();
            $order = Order::find($id);
            ////prevent driver from accepting a cancelled order
            if (empty($order) ) {
                throw new Exception(__("Order could not be found"));
            }else if(!empty($request->driver_id) && in_array($order->status,["cancelled","delivered","failed"])){
                throw new Exception(__("Order has already been")." ".$order->status);
            }else if (empty($order) || (!empty($request->driver_id) && !empty($order->driver_id))) {
                $order->clearFirestore(true);
                throw new Exception(__("Order has been accepted already by another delivery boy"));
            }
            $order->update($request->all());
            
            //
            if (!empty($request->status)) {
                $order->setStatus($request->status);
            }
            $order->clearFirestore(true);
            DB::commit();

            return response()->json([
                "message" => __("Order placed ") . __($order->status) . "",
                "order" => Order::fullData()->where("id", $id)->first(),
            ], 200);
        } catch (\Exception $ex) {

            DB::rollback();
            return response()->json([
                "message" => $ex->getMessage()
            ], 400);
        }
    }










    //
    public function getNewOrderStatus(Request $request)
    {

        $orderDate = Carbon::parse("" . $request->pickup_date . " " . $request->pickup_time . "");
        $hoursDiff = Carbon::now()->diffInHours($orderDate);

        if ($hoursDiff > setting('minScheduledTime', 2)) {
            return "scheduled";
        } else {
            return "pending";
        }
    }

    public function recordWalletDebit($wallet, $amount)
    {
        $walletTransaction = new WalletTransaction();
        $walletTransaction->wallet_id = $wallet->id;
        $walletTransaction->amount = $amount;
        $walletTransaction->reason = __("New Order");
        $walletTransaction->status = "successful";
        $walletTransaction->is_credit = 0;
        $walletTransaction->save();
    }
}
