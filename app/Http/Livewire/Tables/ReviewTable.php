<?php

namespace App\Http\Livewire\Tables;

use App\Models\Review;
use Kdion4891\LaravelLivewireTables\Column;

class ReviewTable extends BaseTableComponent
{

    public $model = Review::class;

    public function query()
    {
        return Review::with('user','driver','vendor');
    }

    public function columns()
    {
        return [
            Column::make(__('ID'),"id")->searchable()->sortable(),
            Column::make(__('Vendor'),'vendor.name')->searchable()->sortable(),
            Column::make(__('User'), 'user.name')->searchable()->sortable(),
            Column::make(__('Driver'), 'driver.name')->searchable()->sortable(),
            Column::make(__('Rating'))->sortable(),
            Column::make(__('Review')),
            Column::make(__('Created At'), 'formatted_date'),
        ];
    }


}
