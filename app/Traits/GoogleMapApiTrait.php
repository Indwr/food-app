<?php
namespace App\Traits;


use Illuminate\Support\Facades\Http;

trait GoogleMapApiTrait
{
    

    public function getTotalDistanceFromGoogle($originLocation, $destinationLocations){
        
        $googleMapDistanceResposne = Http::get('https://maps.googleapis.com/maps/api/distancematrix/json',[
            "key"=> setting("googleMapKey",""),
            "origins"=> $originLocation,
            "destinations"=> $destinationLocations,
        ]);

        if($googleMapDistanceResposne->successful() ){
            $distance = 0;
            $distanceElements = $googleMapDistanceResposne->json()["rows"][0]["elements"];

            foreach ($distanceElements as $distanceElement) {
                $distance += $distanceElement["distance"]["value"];
            }

            return $distance / 1000;
        }else{
            throw new Exception(__("An error occured on our server"), 1);
        }
    }
}
