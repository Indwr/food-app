<?php

namespace App\Models;

use App\Traits\FirebaseMessagingTrait;
use App\Traits\FirebaseDBTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\ModelStatus\HasStatuses;
use Illuminate\Support\Facades\Schema;

class Order extends BaseModel
{

    use FirebaseMessagingTrait, FirebaseDBTrait;
    use HasStatuses;


    protected $fillable = ["note", "reason", "sub_total", "total", "driver_id","delivery_fee"];
    protected $with = ["user", 'statuses', 'stops'];
    protected $appends = ["payment_link", 'formatted_date', 'type', 'formatted_type', 'can_rate', 'can_rate_driver', 'status', 'pickup_location', 'dropoff_location', 'photo'];


    public function scopeFullData($query)
    {
        return $query->with(["products.product", "stops.delivery_address", "user", "driver", "delivery_address", "payment_method", "vendor" => function ($query) {
            return $query->withTrashed();
        }, 'package_type']);
    }

    public function scopeMine($query)
    {
        return $query->when(Auth::user()->hasRole('manager'), function ($query) {
            return $query->where('vendor_id', Auth::user()->vendor_id);
        })->when(Auth::user()->hasRole('city-admin'), function ($query) {
            return $query->whereHas('vendor', function ($query){
                return $query->where('creator_id', Auth::id());
            });
        });
    }

    public function products()
    {
        return $this->hasMany('App\Models\OrderProduct', 'order_id', 'id')->withTrashed();
    }

    public function stops()
    {
        return $this->hasMany('App\Models\OrderStop', 'order_id', 'id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id', 'id');
    }

    public function driver()
    {
        return $this->belongsTo('App\Models\User', 'driver_id', 'id');
    }

    public function delivery_address()
    {
        return $this->belongsTo('App\Models\DeliveryAddress', 'delivery_address_id', 'id');
    }

    public function payment_method()
    {
        return $this->belongsTo('App\Models\PaymentMethod', 'payment_method_id', 'id');
    }

    public function vendor()
    {
        return $this->belongsTo('App\Models\Vendor', 'vendor_id', 'id');
    }

    public function payment()
    {
        return $this->belongsTo('App\Models\Payment', 'id', 'order_id');
    }


    //
    public function package_type()
    {
        return $this->belongsTo('App\Models\PackageType', 'package_type_id', 'id');
    }

    public function getPickupLocationAttribute()
    {
        if (count($this->stops) > 0) {
            return $this->stops->first()->delivery_address;
        } else {
            return null;
        }
    }

    public function getDropoffLocationAttribute()
    {
        if (count($this->stops) > 1) {
            return $this->stops->last()->delivery_address;
        } else {
            return null;
        }
    }

    public function getTypeAttribute()
    {
        return $this->vendor->vendor_type->slug ?? '';
    }

    // public function getIsPackageAttribute()
    // {
    //     if( count($this->stops) > 1  ){
    //         return $this->stops->last();
    //     }else{
    //         return null;
    //     }
    // }





    //
    public function getCanRateAttribute()
    {

        if (empty(Auth::user())) {
            return false;
        }
        //
        $vendorReview = Review::where('user_id', Auth::id())->where('order_id', $this->id)->first();
        return empty($vendorReview);
    }

    public function getCanRateDriverAttribute()
    {

        if (empty(Auth::user())) {
            return false;
        }
        //
        $driverReview = Review::where('user_id', Auth::id())->where('driver_id', $this->driver_id)->first();
        return empty($driverReview);
    }

    public function getPaymentLinkAttribute()
    {

        if ($this->payment_status == "pending") {
            return route('order.payment', ["code" => $this->code]);
        } else {
            return "";
        }
    }

    //TODO
    public function getFormattedTypeAttribute()
    {
        return Str::ucfirst($this->vendor->vendor_type->name ?? '');
    }
    public function getIsPackageAttribute()
    {
        return ($this->vendor->vendor_type->slug ?? '') == "package";
    }





    //updating earning of vendor & driver
    // public function updateEarning()
    // {

    //     //'pending','preparing','ready','enroute','delivered','failed','cancelled'
    //     if ($this->status == 'delivered') {

    //         //update vendor earning
    //         $earning = Earning::firstOrCreate(
    //             ['vendor_id' => $this->vendor_id],
    //             ['amount' => 0]
    //         );

    //         $systemCommission = ($this->vendor->commission / 100) * $this->sub_total;
    //         //minus our commission 
    //         $earning->amount += $this->sub_total - $systemCommission;
    //         $earning->save();



    //         //update driver
    //         if (!empty($this->driver_id)) {

    //             $earning = Earning::firstOrCreate(
    //                 ['user_id' => $this->driver_id],
    //                 ['amount' => 0]
    //             );

    //             $driver = User::find($this->driver_id);
    //             //driver commission from delivery fee + tip from customer
    //             $earnedAmount = (($driver->commission / 100) * $this->delivery_fee) + $this->tip;

    //             logger("Driver Prev Earning", [$earning->amount]);
    //             //if system is using driver wallet
    //             $enableDriverWallet = (bool) setting('enableDriverWallet', "0");
    //             if ($enableDriverWallet) {

    //                 //
    //                 $driverWallet = $this->driver->wallet;
    //                 if (empty($driverWallet)) {
    //                     $driverWallet = $this->driver->updateWallet(0);
    //                 }

    //                 //if driver will be taking cash from customer, we remove the delivery fee from his/her wallet
    //                 if ($this->payment_method->slug == "cash") {
    //                     $driverWallet->balance = $driverWallet->balance - $earnedAmount;
    //                 }
    //                 //if order was paid for via card/wallet, we add the delivery fee to his/her wallet
    //                 else {
    //                     $driverWallet->balance = $driverWallet->balance + $earnedAmount;
    //                 }

    //                 //
    //                 $driverWallet->save();
    //             } else {
    //                 $earning->amount = $earning->amount + $earnedAmount;
    //             }
    //             $earning->save();
    //             logger("Driver New Earning", [$earning->amount]);
    //         }
    //     }
    // }

    //updating wallet balance is order failed and was paid via wallet
    public function refundUser()
    {
        //'pending','preparing','ready','enroute','delivered','failed','cancelled'
        if (in_array($this->status, ['failed', 'cancelled']) && in_array($this->payment_status, ['successful'])  && $this->payment_method->slug != "cash") {

            //update user wallet
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $this->user_id],
                ['balance' => 0]
            );

            //
            $wallet->balance += $this->total;
            $wallet->save();

            //save wallet transactions
            $walletTransaction = new WalletTransaction();
            $walletTransaction->wallet_id = $wallet->id;
            $walletTransaction->amount = $this->total;
            $walletTransaction->reason = "Refund";
            $walletTransaction->status = "successful";
            $walletTransaction->is_credit = 1;
            $walletTransaction->save();
        }
    }

    public function notifyDeliveryBoys()
    {

        if ($this->status == "ready" && !empty($this->delivery_address_id)) {
            if ($this->vendor->auto_assignment) {
                $this->saveToFCDB($this);
            }
        }
        //for parcel auto
        else if (
            $this->payment_status == "successful" &&
            in_array($this->status, ["pending", "preparing"]) &&
            !empty($this->package_type_id)
        ) {

            $packageTypePricing = PackageTypePricing::where('vendor_id', $this->vendor_id)
                ->where('package_type_id', $this->package_type_id)->first();

            if (!empty($packageTypePricing) && $packageTypePricing->auto_assignment) {
                $this->saveToFCDB($this);
            }
        }
    }

    public function clearFirestore(bool $force = false)
    {
        // if ($this->status == "enroute" || $force) {
        //     if ($this->vendor->auto_assignment) {
        //         $this->deleteFromFCDB($this);
        //     }
        // }
        if ($this->status == "enroute" || !empty($this->driver_id) || $force) {
            $this->deleteFromFCDB($this);
        }
        //clear data from firestore 
        else if(in_array($this->status,['failed', 'cancelled', 'delivered'])){
            $this->deleteOrderFromFCDB($this);
        }
    }
}
