<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Carbon\Carbon;

class AutoCancelOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:cancel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel pending order when the time is right';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        //
        $today = Carbon::today();
        $hoursFromNow = Carbon::now()->addMinutes(setting('autoCancelPendingOrderTime', 30))->toTimeString();

        //get orders pending for more the ``autoCancelPendingOrderTime``
        $orders = Order::currentStatus('pending')
            ->whereTime('updated_at', '<=', $hoursFromNow)->limit(20)->get();

        foreach ($orders as $order) {
            $order->setStatus('cancelled');
        }
    }
}
