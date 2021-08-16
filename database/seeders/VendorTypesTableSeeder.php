<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class VendorTypesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('vendor_types')->delete();
        
        \DB::table('vendor_types')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'Parcel Delivery',
                'description' => 'Send parcel to people',
                'slug' => 'parcel',
                'is_active' => 1,
                'created_at' => '2021-06-30 10:45:53',
                'updated_at' => '2021-06-30 10:45:53',
                'deleted_at' => NULL,
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'Food Delivery Sweet Foods',
                'description' => 'Buy the best meal from your nearby restaurant',
                'slug' => 'food',
                'is_active' => 1,
                'created_at' => '2021-06-30 10:45:53',
                'updated_at' => '2021-06-30 19:08:42',
                'deleted_at' => NULL,
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'Grocery',
                'description' => 'buy grocery from your nearby markets',
                'slug' => 'grocery',
                'is_active' => 1,
                'created_at' => '2021-06-30 13:59:15',
                'updated_at' => '2021-06-30 13:59:15',
                'deleted_at' => NULL,
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'Pharmacy',
                'description' => 'buy drugs for your sickness and get it delivered directly to your doorstep',
                'slug' => 'pharmacy',
                'is_active' => 1,
                'created_at' => '2021-06-30 14:01:27',
                'updated_at' => '2021-06-30 14:01:27',
                'deleted_at' => NULL,
            ),
            4 => 
            array (
                'id' => 5,
                'name' => 'Services',
                'description' => 'for vendor selling services',
                'slug' => 'service',
                'is_active' => 0,
                'created_at' => '2021-07-15 00:38:10',
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ),
        ));
        
        
    }
}