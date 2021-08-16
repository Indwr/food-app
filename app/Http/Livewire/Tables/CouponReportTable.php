<?php

namespace App\Http\Livewire\Tables;

use App\Models\CouponUser;
use Kdion4891\LaravelLivewireTables\Column;

class CouponReportTable extends BaseTableComponent
{

    public $model = CouponUser::class;

    public function query()
    {
        return CouponUser::with('user','coupon','order');
    }

    public function columns()
    {
        return [
            Column::make(__('ID'),"id"),
            Column::make(__('Code'),'coupon.code')->searchable()->sortable(),
            Column::make(__('Discount')."(".setting('currency', '$').")",'order.discount')->searchable()->sortable(),
            Column::make(__('User'),'user.name'),
            Column::make(__('Order'),'order.code'),
            Column::make(__('Date'),'order.created_at'),
        ];
    }
}
