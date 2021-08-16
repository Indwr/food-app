<?php

namespace App\Models;

class VendorType extends BaseModel
{


    protected $appends = ['formatted_date', 'logo'];

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection('logo')
            ->useFallbackUrl('' . url('') . '/images/default.png')
            ->useFallbackPath(public_path('/images/default.png'));
        $this
            ->addMediaCollection('feature_image')
            ->useFallbackUrl('' . url('') . '/images/default.png')
            ->useFallbackPath(public_path('/images/default.png'));
    }
    

    public function getLogoAttribute()
    {
        return $this->getFirstMediaUrl('logo');
    }

    public function getIsParcelAttribute()
    {
        return $this->slug == "parcel";
    }
    
}
