<?php

namespace App\Http\Livewire\Tables;

use App\Models\Order;
use App\Models\User;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

use Illuminate\Support\Facades\Auth;


class OrderTable extends BaseDataTableComponent
{


    public $header_view = 'components.buttons.new';
    public $per_page = 10;



    public function query()
    {

        $user = User::find(Auth::id());
        if ($user->hasRole('admin')) {
            return Order::fullData()->orderBy('id', "DESC");
        } else if ($user->hasRole('city-admin')) {
            return Order::with('vendor')->whereHas("vendor", function ($query) {
                return $query->where('creator_id', Auth::id());
            })->fullData()->orderBy('id', "DESC");
        } else {
            return Order::fullData()->where('vendor_id', Auth::user()->vendor_id)->orderBy('id', "DESC");
        }
    }

    public function columns(): array
    {

        $columns = [
            Column::make(__('ID'),'id'),
            Column::make(__('Code'), 'code')->searchable()->sortable(),
            Column::make(__('User'), 'user.name')->searchable()->sortable(),
            Column::make(__('Status'), 'status')
                ->format(function ($value, $column, $row) {
                    return view('components.table.custom', $data = [
                        "value" => \Str::ucfirst($row->status)
                    ]);
                }),
            Column::make(__('Payment Status'), 'payment_status')
            ->format(function ($value, $column, $row) {
                return view('components.table.custom', $data = [
                    "value" => \Str::ucfirst($row->payment_status)
                ]);
            })->searchable()->sortable(),
            Column::make(__('Total'))->format(function ($value, $column, $row) {
                return view('components.table.order-total', $data = [
                    "model" => $row
                ]);
            })->searchable()->sortable(),
            Column::make('Method', 'payment_method.name')->searchable(),
        ];

        //
        if (Auth::user()->hasAnyRole('admin', 'city-admin')) {
            // array_push($columns, Column::make(__('Vendor'), 'vendor.name'));
        }

        array_push($columns, Column::make(__('Created At'), 'formatted_date'));
        array_push($columns, Column::make(__('Actions'))->format(function ($value, $column, $row) {
            return view('components.buttons.order_actions', $data = [
                "model" => $row
            ]);
        }));
        return $columns;
    }
}
