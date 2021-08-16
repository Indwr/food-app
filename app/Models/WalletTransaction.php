<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class WalletTransaction extends BaseModel
{
    use SoftDeletes;
    protected $with = ["wallet"];
    protected $fillable = ["amount", "ref", "session_id", "wallet_id", "payment_method_id", "status", "is_credit"];

    public function wallet()
    {
        return $this->belongsTo('App\Models\Wallet', 'wallet_id', 'id');
    }

    public function payment_method()
    {
        return $this->belongsTo('App\Models\PaymentMethod', 'payment_method_id', 'id');
    }
}
