<?php

namespace App\Http\Livewire\Tables;

use App\Models\Vendor;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

use Illuminate\Support\Facades\Auth;

class VendorTable extends BaseDataTableComponent
{

    public $model = Vendor::class;
    public bool $columnSelect = false;    

    public function query()
    {
        return Vendor::with('vendor_type')->mine()->orderBy('id','desc');
    }

    public function columns(): array
    {
        return [
            Column::make(__('ID'),'id')->searchable()->sortable(),
            Column::make(__('Logo'))->format(function ($value, $column, $row) {
                return view('components.table.logo', $data = [
                    "model" => $row
                ]);
            }),
            Column::make(__('Name'), 'name')->searchable()->sortable(),
            Column::make(__('Type'), 'vendor_type.name'),
            Column::make(__('Active'))->format(function ($value, $column, $row) {
                return view('components.table.active', $data = [
                    "model" => $row
                ]);
            }),
            Column::make(__('Created At'), 'formatted_date'),
            Column::make(__('Actions'))->format(function ($value, $column, $row) {
                return view('components.buttons.market_actions', $data = [
                    "model" => $row
                ]);
            }),
        ];
    }

    //
    public function deleteModel()
    {

        try {
            $this->isDemo();
            \DB::beginTransaction();
            $this->selectedModel->delete();
            \DB::commit();
            $this->showSuccessAlert("Deleted");
        } catch (Exception $error) {
            \DB::rollback();
            $this->showErrorAlert($error->getMessage() ?? "Failed");
        }
    }
}
