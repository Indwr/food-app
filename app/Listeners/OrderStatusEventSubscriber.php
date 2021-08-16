<?php

namespace App\Listeners;

use App\Models\Order;
use Carbon\Carbon;
use Spatie\ModelStatus\Status;

class OrderStatusEventSubscriber
{
    /** @var \Spatie\ModelStatus\Status|null */
    public $oldStatus;

    /** @var \Spatie\ModelStatus\Status */
    public $newStatus;

    /** @var \Illuminate\Database\Eloquent\Model */
    public $model;

    public function __construct(?Status $oldStatus, Status $newStatus, Order $model)
    {
        $this->oldStatus = $oldStatus;

        $this->newStatus = $newStatus;

        $this->model = $model;
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function subscribe($events)
    {
        //
        $events->listen(
            'Spatie\ModelStatus\Events\StatusUpdated',
            [OrderStatusEventSubscriber::class, 'handleOrderUpdate']
        );
    }

    public function handleOrderUpdate($event)
    {
        //set the correct dateTime from carbon
        $oldStatus = $event->oldStatus;
        $newStatus = $event->newStatus;
        // logger("Order Status", [
        //     [
        //        "Order Id" => $event->model->id ?? "",
        //     ],
        //     $oldStatus != null ? $oldStatus->name : "",
        //     $newStatus->name, 
        //     now(),
        // ]);
        //
        if( ($oldStatus != null ? $oldStatus->name : "") != $newStatus->name ){
            $event->model->updated_at = \Carbon\Carbon::now();
            $event->model->save();
        }
        
    }
}
