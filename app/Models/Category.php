<?php

namespace App\Models;

class Category extends BaseModel
{

    protected $fillable = [
        'id','name', 'vendor_type_id', 'is_active'
    ];

    public function vendor_type()
    {
        return $this->belongsTo('App\Models\VendorType', 'vendor_type_id', 'id');
    }

    public function vendors()
    {
        return $this->hasMany('App\Models\Vendor');
    }

    public function sub_categories()
    {
        return $this->hasMany('App\Models\Subcategory');
    }

}
