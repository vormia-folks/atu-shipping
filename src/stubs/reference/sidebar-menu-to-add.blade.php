{{-- ATU Shipping — inject inside your Flux admin sidebar (e.g. within flux:sidebar.group), before </flux:sidebar.group> --}}
{{-- Requires livewire/flux in the host app (e.g. vormiaphp/ui-livewireflux-admin). --}}

<flux:sidebar.item icon="truck" :href="route('admin.atu.shipping.couriers.index')" wire:navigate>
    {{ __('Shipping couriers') }}
</flux:sidebar.item>
<flux:sidebar.item icon="rectangle-stack" :href="route('admin.atu.shipping.rules.index')" wire:navigate>
    {{ __('Shipping rules') }}
</flux:sidebar.item>
<flux:sidebar.item icon="clipboard-document-list" :href="route('admin.atu.shipping.logs.index')" wire:navigate>
    {{ __('Shipping logs') }}
</flux:sidebar.item>
