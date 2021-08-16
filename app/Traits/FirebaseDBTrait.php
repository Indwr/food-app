<?php

namespace App\Traits;

use MrShan0\PHPFirestore\FirestoreClient;
use MrShan0\PHPFirestore\FirestoreDocument;
use MrShan0\PHPFirestore\Fields\FirestoreObject;
use AnthonyMartin\GeoLocation\GeoPoint;
use App\Models\Order;

trait FirebaseDBTrait
{

    use FirebaseAuthTrait;

    public function saveToFCDB($order)
    {


        // if( empty($order->delivery_address_id) && empty($order->pickup_location_id)  ){
        //     return;
        // }

        try {
            $firestoreClient = $this->getFirestoreClient();

            //
            $pickupLocationLat = $order->type != "parcel" ? $order->vendor->latitude : $order->pickup_location->latitude;
            $pickupLocationLng = $order->type != "parcel" ? $order->vendor->longitude : $order->pickup_location->longitude;

            $document = new FirestoreDocument;
            $document->setObject('pickup', new FirestoreObject(
                [
                    'lat' => $pickupLocationLat,
                    'long' => $pickupLocationLng,
                    'address' => $order->type != "parcel" ? $order->vendor->address : $order->pickup_location->address,
                    'city' => $order->type != "parcel" ? "" : $order->pickup_location->city,
                    'state' => $order->type != "parcel" ? "" : $order->pickup_location->state ?? "",
                    'country' => $order->type != "parcel" ? "" : $order->pickup_location->country ?? "",
                ]
            ));
            $document->setObject('dropoff', new FirestoreObject(
                [
                    'lat' => $order->type != "parcel" ? $order->delivery_address->latitude : $order->dropoff_location->latitude,
                    'long' => $order->type != "parcel" ? $order->delivery_address->longitude : $order->dropoff_location->longitude,
                    'address' => $order->type != "parcel" ? $order->delivery_address->address : $order->dropoff_location->address,
                    'city' => $order->type != "parcel" ? "" : $order->dropoff_location->city,
                    'state' => $order->type != "parcel" ? "" : $order->pickup_location->state ?? "",
                    'country' => $order->type != "parcel" ? "" : $order->pickup_location->country ?? "",
                ]
            ));
            //amount
            $document->setString('amount', (string)$order->delivery_fee);
            $document->setString('total', (string)$order->total);
            $document->setInteger('vendor_id', $order->vendor_id);
            $document->setBoolean('is_parcel', $order->type == "parcel");
            if ($order->type == "parcel") {
                $document->setString('package_type', $order->package_type->name);
            }
            $document->setInteger('id', $order->id);
            $document->setInteger('range', $order->vendor->delivery_range);

            //EARTH DISTANCE 
            $geopointA = new GeoPoint($pickupLocationLat, $pickupLocationLng);
            $geopointB = new GeoPoint(0.00, 0.00);
            $earthDistance = $geopointA->distanceTo($geopointB, 'kilometers');
            $document->setInteger('earth_distance', $earthDistance);

            $firestoreClient->addDocument("newOrders", $document, $order->code);
        } catch (\Exception $ex) {
            logger("Error", [$ex]);
        }
    }

    //
    public function deleteFromFCDB($order)
    {

        try {
            $firestoreClient = $this->getFirestoreClient();
            $firestoreClient->deleteDocument("newOrders/" . $order->code . "");
        } catch (\Exception $ex) {
            logger("Error", [$ex]);
        }
    }

    public function clearNewOrdersFCDB()
    {

        try {
            $firestoreClient = $this->getFirestoreClient();
            $newOrders = $firestoreClient->listDocuments('newOrders')["documents"];
            foreach ($newOrders as $newOrder) {
                $firestoreClient->deleteDocument($newOrder);
            }
        } catch (\Exception $ex) {
            logger("Error", [$ex]);
        }
    }

    public function deleteOrderFromFCDB($order)
    {

        try {
            $firestoreClient = $this->getFirestoreClient();
            $firestoreClient->deleteDocument("orders/" . $order->code . "");
            $this->deleteFromFCDB($order);
        } catch (\Exception $ex) {
            logger("Error", [$ex]);
        }
    }

    //
    public function updateDriverCurrentOrderNumber($order)
    {


        if (empty($order->driver_id)) {
            return;
        }

        try {

            //
            $totalOrders = Order::where("driver_id", $order->driver_id)->otherCurrentStatus(['failed', 'cancelled', 'delivered'])->count();
            $firestoreClient = $this->getFirestoreClient();

            $firestoreClient->updateDocument("drivers/" . $order->driver_id , ['on' => $totalOrders]);
        } catch (\Exception $ex) {
            logger("Error", [$ex]);
        }
    }
}
