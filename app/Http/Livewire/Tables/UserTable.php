<?php

namespace App\Http\Livewire\Tables;

use App\Models\DeliveryAddress;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Auth;
use Kdion4891\LaravelLivewireTables\Column;

class UserTable extends BaseTableComponent
{

    public $model = User::class;
    public $role;
    public $header_view = 'components.buttons.new';

    public function query()
    {
        $user = User::find(Auth::id());
        if ($user->hasRole('admin')) {
            return User::with('roles','creator')->whereHas('roles', function($query){
                if(!empty($this->role)){
                return $query->where('name', $this->role);
                }
            });
        } else {
            return User::with('roles','creator')->whereHas('roles', function($query){
                if(!empty($this->role)){
                return $query->where('name', $this->role);
                }
            })->where('creator_id', Auth::id());
        }
    }

    public function filterUsers($role){
        $this->role = $role;
    }

    public function columns()
    {
        return [
            Column::make(__('ID'),"id")->searchable()->sortable(),
            Column::make(__('Name'),'name')->searchable()->sortable(),
            Column::make(__('Phone'))->searchable()->sortable(),
            Column::make(__('Creator'),'creator.name'),
            Column::make(__('Wallet'))->view('components.table.wallet'),
            Column::make(__('Commission')."(%)", 'commission'),
            Column::make(__('Role'), 'role_name'),
            Column::make(__('Created At'), 'formatted_date'),
            Column::make(__('Actions'))->view('components.buttons.user_actions'),
        ];
    }

    //
    public function deleteModel()
    {

        try {
            
            $this->isDemo();
            \DB::beginTransaction();
            //
            $walletIds = Wallet::where('user_id', $this->selectedModel->id)->get()->pluck('id');
            DeliveryAddress::whereIn('user_id', [$this->selectedModel->id])->delete();
            WalletTransaction::whereIn('wallet_id', $walletIds)->delete();
            Wallet::whereIn('id', $walletIds)->delete();
            $this->selectedModel = $this->selectedModel->fresh();
            $this->selectedModel->delete();
            \DB::commit();
            $this->showSuccessAlert("Deleted");
        } catch (Exception $error) {
            \DB::rollback();
            $this->showErrorAlert($error->getMessage() ?? "Failed");
        }
    }
}
