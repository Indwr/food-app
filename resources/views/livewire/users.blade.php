@section('title', __('Users') )
<div>

    <x-baseview title="{{ __('Users') }}">
        <div class="flex items-center my-4 space-x-4">
            <div class="cursor-pointer px-4 py-2 {{ $selectRole == '' ? 'text-white bg-gray-500':'text-black bg-gray-300' }} rounded" wire:click="sortList('')">All</div>
            @foreach($roles as $role)
                <div class="cursor-pointer px-4 py-2 {{ $selectRole == $role->name ? 'text-white bg-gray-500':'text-black bg-gray-300' }}  rounded" wire:click="sortList('{{ $role->name }}')">{{ ucfirst($role->name) }}</div>
            @endforeach
        </div>
        <livewire:tables.user-table />
    </x-baseview>

    <div x-data="{ open: @entangle('showCreate') }">
        <x-modal confirmText="{{ __('Save') }}" action="save">
            <p class="text-xl font-semibold">{{ __('Create User Account') }}</p>
            <x-input title="{{ __('Name') }}" name="name" placeholder="John" />
            <x-input title="{{ __('Email') }}" name="email" placeholder="info@mail.com" />
            <x-input title="{{ __('Phone') }}" name="phone" placeholder="" />
            <x-input title="{{ __('Login Password') }}" name="password" type="password"
                placeholder="**********************" />
            <x-select title="{{ __('Role') }}" :options='$roles' name="role" :defer="false" />
            
            @if( ($roleName ?? "") == "driver")
                <x-input title="{{ __('Commission') }}" name="commission" placeholder="" />
            @endif
            <x-input title="{{ __('Wallet Balance') }}" name="walletBalance" placeholder="" />
        </x-modal>
    </div>

    <div x-data="{ open: @entangle('showEdit') }">
        <x-modal confirmText="Update" action="update">

            <p class="text-xl font-semibold">{{ __('Edit User Account') }}</p>
            <x-input title="{{ __('Name') }}" name="name" placeholder="John" />
            <x-input title="{{ __('Email') }}" name="email" placeholder="info@mail.com" />
            <x-input title="{{ __('Phone') }}" name="phone" placeholder="" />
            <x-input title="{{ __('Login Password') }}" name="password" type="password"
                placeholder="**********************" />
            <x-select title="{{ __('Role') }}" :options='$roles' name="role"
                selected="{{ !empty($selectedModel) ? $selectedModel->role_id : '' }}" />
                @if( ($roleName ?? "") == "driver")
                    <x-input title="{{ __('Commission') }}" name="commission" placeholder="" />
                @endif
            <x-input title="{{ __('Wallet Balance') }}" name="walletBalance" placeholder="" />

        </x-modal>
    </div>

    {{-- assign form --}}
    <div x-data="{ open: @entangle('showAssign') }">
        <x-modal confirmText="{{ __('Assign') }}" action="assignVendors" :clickAway="false">

            <p class="text-xl font-semibold">{{ __('Assign Vendors To City Admin') }}</p>
            <x-select2 title="{{ __('Vendors') }}" :options="$vendors" name="vendorsIDs"
                id="vendorsSelect2" :multiple="true" width="100" :ignore="true" />

        </x-modal>
    </div>
</div>
