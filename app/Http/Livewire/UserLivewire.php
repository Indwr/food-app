<?php

namespace App\Http\Livewire;

use App\Models\User;
use App\Models\Vendor;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UserLivewire extends BaseLivewireComponent
{

    //
    public $model = User::class;
    public $selectRole;

    //
    public $name;
    public $email;
    public $phone;
    public $password;
    public $role;
    public $commission;
    public $walletBalance;
    //
    public $vendorsIDs;
    public $roleName;


    protected $rules = [
        "role" => "required",
        "name" => "required|string",
        "email" => "required|email|unique:users",
        "phone" => "required|unique:users",
        "password" => "sometimes|nullable|string",
        "commission" => "sometimes|nullable|numeric",
        "walletBalance" => "sometimes|nullable|numeric",
    ];


    protected $messages = [
        "email.unique" => "Email already associated with any account",
        "phone.unique" => "Phone Number already associated with any account",
    ];

    public function render()
    {
        return view('livewire.users', [
            "roles" => $this->getRoles(),
            "vendors" => Vendor::active()->get(),
        ]);
    }

    public function getRoles()
    {
        $user = User::find(\Auth::id());
        $roles = [];
        if ($user->hasRole('admin')) {
            $roles = Role::all();
        } else {
            $roles = Role::where('name', 'not like', '%admin%')->get();
        }
        return $roles;
    }

    public function updatedRole($value){
        $this->roleName = Role::where('id', $value)->get()->first()->name;
    }

    public function sortList($role)
    {
        $this->selectRole = $role;
        $this->emit('filterUsers', $role);
    }

    public function showCreateModal()
    {
        $this->reset();
        $this->role = $this->getRoles()->first->name;
        $this->showCreate = true;
    }

    public function save()
    {
        //validate
        $this->validate();

        try {

            DB::beginTransaction();
            $user = new User();
            $user->name = $this->name;
            $user->email = $this->email;
            $user->phone = $this->phone;
            $user->creator_id = \Auth::id();
            $user->commission = $this->commission ?? 0.00;
            $user->password = Hash::make($this->password);
            $user->save();

            if (!empty($this->role)) {
                $user->assignRole($this->role);
            }

            //update wallet
            $user->updateWallet($this->walletBalance);

            DB::commit();

            $this->dismissModal();
            $this->reset();
            $this->showSuccessAlert(__("User") . " " . __('created successfully!'));
            $this->emit('refreshTable');
        } catch (Exception $error) {
            logger("User Create Error", [$error]);
            DB::rollback();
            $this->showErrorAlert($error->getMessage() ?? __("User") . " " . __('creation failed!'));
        }
    }

    public function initiateEdit($id)
    {
        $this->reset();
        $this->selectedModel = $this->model::find($id);
        $this->name = $this->selectedModel->name;
        $this->email = $this->selectedModel->email;
        $this->phone = $this->selectedModel->phone;
        $this->role = $this->selectedModel->role_id;
        $this->commission = $this->selectedModel->commission;
        $this->walletBalance = $this->selectedModel->wallet->balance ?? 0.00;
        $this->emit('showEditModal');
    }

    public function update()
    {
        //validate
        $this->validate(
            [
                "name" => "required|string",
                "email" => "required|email|unique:users,email," . $this->selectedModel->id . "",
                "phone" => "required|unique:users,phone," . $this->selectedModel->id . "",
                "password" => "sometimes|nullable|string",
                "commission" => "sometimes|nullable|numeric",
                "walletBalance" => "sometimes|nullable|numeric",
            ]
        );

        try {

            DB::beginTransaction();
            $user = $this->selectedModel;
            $user->name = $this->name;
            $user->email = $this->email;
            $user->phone = $this->phone;
            $user->commission = $this->commission ?? 0.00;
            if (!empty($this->password)) {
                $user->password = Hash::make($this->password);
            }
            $user->save();

            if (!empty($this->role)) {
                $user->syncRoles($this->role);
            }


            //update wallet
            $user->updateWallet($this->walletBalance);

            DB::commit();

            $this->dismissModal();
            $this->reset();
            $this->showSuccessAlert(__("User") . " " . __('updated successfully!'));
            $this->emit('refreshTable');
        } catch (Exception $error) {
            DB::rollback();
            $this->showErrorAlert($error->getMessage() ?? __("User") . " " . __('updated failed!'));
        }
    }


    // Assigning vendors
    public function initiateAssign($id)
    {
        $this->selectedModel = $this->model::find($id);
        $this->vendorsIDs = $this->selectedModel->vendors->pluck('id');
        $this->emit('showAssignModal');
        $this->showSelect2("#vendorsSelect2", $this->vendorsIDs, "vendorsChange");
    }

    public function assignVendors()
    {
        try {

            DB::beginTransaction();

            //assigning
            foreach ($this->vendorsIDs as $vendorsID) {
                $vendor = Vendor::findorfail($vendorsID);
                $vendor->creator_id = $this->selectedModel->id;
                $vendor->save();
            }

            DB::commit();
            $this->emit('dismissModal');
            $this->showSuccessAlert(__("Vendor City Admin") . " " . __('updated successfully!'));
        } catch (Exception $error) {
            DB::rollback();
            $this->showErrorAlert($error->getMessage() ?? __("Vendor City Admin") . " " . __('updated failed!'));
        }
    }

    public function vendorsChange($data)
    {
        $this->vendorsIDs = $data;
    }
}
