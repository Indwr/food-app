<?php

namespace App\Services;

use App\Models\Earning;
use App\Models\Remittance;
use App\Models\Wallet;
use App\Models\User;

class OrderEarningService
{
    public function __constuct()
    {
        //
    }


    public function updateEarning($order)
    {

        $isCashOrder = $order->payment_method->slug == "cash";
        //'pending','preparing','ready','enroute','delivered','failed','cancelled'
        if ($order->status == 'delivered') {

            //update vendor earning 
            //only if online or driver wallet
            $enableDriverWallet = (bool) setting('enableDriverWallet', "0");
            if (!$isCashOrder || !$enableDriverWallet) {
                $earning = Earning::firstOrCreate(
                    ['vendor_id' => $order->vendor_id],
                    ['amount' => 0]
                );

                $systemCommission = ($order->vendor->commission / 100) * $order->sub_total;
                //minus our commission 
                $earning->amount += $order->sub_total - $systemCommission - ($order->discount ?? 0);
                $earning->save();
            }



            //update driver
            if (!empty($order->driver_id)) {

                $driverEarning = Earning::firstOrCreate(
                    ['user_id' => $order->driver_id],
                    ['amount' => 0]
                );

                $driver = User::find($order->driver_id);
                //driver commission from delivery fee + tip from customer
                $earnedAmount = (($driver->commission / 100) * $order->delivery_fee) + $order->tip;

                //if system is using driver wallet
                //if its online order payment
                if (!$isCashOrder) {
                    $driverEarning->amount = $driverEarning->amount + $earnedAmount;
                } else  if ($enableDriverWallet) {

                    //
                    $driverWallet = $order->driver->wallet;
                    if (empty($driverWallet)) {
                        $driverWallet = $order->driver->updateWallet(0);
                    }

                    //
                    $totalToDeduct  = $order->total - $earnedAmount;
                    $driverWallet->balance = $driverWallet->balance - $totalToDeduct;

                    //
                    $driverWallet->save();
                } else {
                    $driverEarning->amount = $driverEarning->amount + $earnedAmount;
                    //save the record of the order that needs to be collected fromm driver
                    //log the order for driver remittance 
                    $remittance = new Remittance();
                    $remittance->user_id = $order->driver_id;
                    $remittance->order_id = $order->id;
                    $remittance->save();
                }
                $driverEarning->save();
            }
        }
    }
}
