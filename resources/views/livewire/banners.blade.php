@section('title',  __('Banners') )
<div>

    <x-baseview title="{{ __('Banners') }}">
        <livewire:tables.banner-table />
    </x-baseview>

    <div x-data="{ open: @entangle('showCreate') }">
        <x-modal confirmText="{{ __('Save') }}" action="save">
            <p class="text-xl font-semibold">{{ __('Create Banner') }}</p>
            <x-media-upload
                        title="{{ __('Photo') }}"
                        name="photo"
                        :photo="$photo"
                        :photoInfo="$photoInfo"
                        types="PNG or JPEG"
                        rules="image/*" />

            <x-select title="{{ __('Category') }}" :options='$categories' name="category_id" :defer="true" />
            <x-checkbox
                    title="{{ __('Active') }}"
                    name="isActive" :defer="false" />

        </x-modal>
    </div>

    <div x-data="{ open: @entangle('showEdit') }">
        <x-modal confirmText="{{ __('Update') }}" action="update">

            <p class="text-xl font-semibold">{{ __('Edit Banner') }}</p>
            <x-media-upload
                        title="{{ __('Photo') }}"
                        name="photo"
                        preview="{{ $selectedModel->photo ?? '' }}"
                        :photo="$photo"
                        :photoInfo="$photoInfo"
                        types="PNG or JPEG"
                        rules="image/*" />
            <x-select title="{{ __('Category') }}" :options='$categories' name="category_id" selected="{{ !empty($selectedModel) ? $selectedModel->category_id : '' }}"  :defer="true" />
            <x-checkbox
                    title="{{ __('Active') }}"
                    name="isActive" :defer="false" />


        </x-modal>
    </div>
</div>


