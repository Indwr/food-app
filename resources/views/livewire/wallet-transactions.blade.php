@section('title', __('Wallet Transactions') )
<div>

    <x-baseview title="{{ __('Wallet Transactions') }}">
        <livewire:tables.wallet-transaction-table />
    </x-baseview>

</div>


