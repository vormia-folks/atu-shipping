<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\DB;
use App\Traits\Vrm\Livewire\WithNotifications;

new class extends Component {
    use WithNotifications;

    #[Validate('required|string|max:255')]
    public $name = '';

    #[Validate('required|string|max:50|unique:atu_shipping_couriers,code')]
    public $code = '';

    #[Validate('nullable|string')]
    public $description = '';

    #[Validate('required|boolean')]
    public $is_active = true;

    public function save()
    {
        $this->validate();

        try {
            // Check if code already exists
            $exists = DB::table('atu_shipping_couriers')
                ->where('code', strtolower($this->code))
                ->exists();

            if ($exists) {
                $this->notifyError(__('Courier code already exists.'));
                return;
            }

            DB::table('atu_shipping_couriers')->insert([
                'name' => $this->name,
                'code' => strtolower($this->code),
                'description' => $this->description,
                'is_active' => $this->is_active,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->notifySuccess(__('Courier created successfully!'));
            return $this->redirect(route('admin.atu.shipping.couriers.index'), navigate: true);
        } catch (\Exception $e) {
            $this->notifyError(__('Failed to create courier: ' . $e->getMessage()));
        }
    }

    public function cancel(): void
    {
        $this->reset(['name', 'code', 'description', 'is_active']);
        $this->notifyInfo(__('Creation cancelled!'));
    }
};

?>

<div>
    <x-admin-panel>
        <x-slot name="header">{{ __('Add New Courier') }}</x-slot>
        <x-slot name="desc">
            {{ __('Add a new shipping courier to the system.') }}
        </x-slot>
        <x-slot name="button">
            <a href="{{ route('admin.atu.shipping.couriers.index') }}"
                class="bg-black dark:bg-gray-700 text-white hover:bg-gray-800 dark:hover:bg-gray-600 px-3 py-2 rounded-md float-right text-sm font-bold">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-4 inline-block">
                    <path fill-rule="evenodd"
                        d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm-4.28 9.22a.75.75 0 0 0 0 1.06l3 3a.75.75 0 1 0 1.06-1.06l-1.72-1.72h5.69a.75.75 0 0 0 0-1.5h-5.69l1.72-1.72a.75.75 0 0 0-1.06-1.06l-3 3Z"
                        clip-rule="evenodd" />
                </svg>
                Go Back
            </a>
        </x-slot>

        {{-- Form Container --}}
        <div class="overflow-hidden shadow-sm ring-1 ring-black/5 dark:ring-white/10 sm:rounded-lg px-4 py-5 mb-5 sm:p-6">
            {{-- Display notifications --}}
            {!! $this->renderNotification() !!}

            <form wire:submit="save">
                <div class="space-y-12">
                    <div class="grid grid-cols-1 gap-x-8 gap-y-10 pb-12 md:grid-cols-3">
                        {{-- Left Column: Field Descriptions --}}
                        <div>
                            <h2 class="text-base/7 font-semibold text-gray-900 dark:text-gray-100">Courier Information</h2>
                            <p class="mt-1 text-sm/6 text-gray-600 dark:text-gray-300">
                                Enter the basic information for the shipping courier.
                            </p>
                        </div>

                        {{-- Right Column: Form Fields --}}
                        <div class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6 md:col-span-2">
                            {{-- Name Field --}}
                            <div class="col-span-full">
                                <label for="name" class="block text-sm/6 font-medium text-gray-900 dark:text-gray-100 required">Name</label>
                                <div class="mt-2">
                                    <div class="flex items-center rounded-md bg-white dark:bg-gray-700 pl-3 outline-1 -outline-offset-1 outline-gray-300 dark:outline-gray-600 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-indigo-600">
                                        <input type="text" id="name" wire:model="name" placeholder="e.g. DHL Express"
                                            class="block min-w-0 grow py-1.5 pr-3 pl-1 text-base text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:outline-none sm:text-sm/6" />
                                    </div>
                                    <span class="text-red-500 text-sm italic"> {{ $errors->first('name') }}</span>
                                </div>
                            </div>

                            {{-- Code Field --}}
                            <div class="col-span-full">
                                <label for="code" class="block text-sm/6 font-medium text-gray-900 dark:text-gray-100 required">Code</label>
                                <div class="mt-2">
                                    <div class="flex items-center rounded-md bg-white dark:bg-gray-700 pl-3 outline-1 -outline-offset-1 outline-gray-300 dark:outline-gray-600 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-indigo-600">
                                        <input type="text" id="code" wire:model="code" placeholder="e.g. dhl"
                                            class="block min-w-0 grow py-1.5 pr-3 pl-1 text-base text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:outline-none sm:text-sm/6" />
                                    </div>
                                    <span class="text-red-500 text-sm italic"> {{ $errors->first('code') }}</span>
                                </div>
                                <p class="mt-3 text-sm/6 text-gray-600 dark:text-gray-300">Unique identifier for this courier (lowercase, no spaces)</p>
                            </div>

                            {{-- Description Field --}}
                            <div class="col-span-full">
                                <label for="description" class="block text-sm/6 font-medium text-gray-900 dark:text-gray-100">Description</label>
                                <div class="mt-2">
                                    <textarea id="description" wire:model="description" rows="3"
                                        class="block w-full rounded-md bg-white dark:bg-gray-700 px-3 py-1.5 text-base text-gray-900 dark:text-gray-100 outline-1 -outline-offset-1 outline-gray-300 dark:outline-gray-600 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6"
                                        placeholder="Optional description of the courier service"></textarea>
                                    <span class="text-red-500 text-sm italic"> {{ $errors->first('description') }}</span>
                                </div>
                                <p class="mt-3 text-sm/6 text-gray-600 dark:text-gray-300">Write a description for the courier.</p>
                            </div>

                            {{-- Active Status --}}
                            <div class="col-span-full">
                                <label class="flex items-center">
                                    <input type="checkbox" wire:model="is_active"
                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600">
                                    <span class="ml-2 text-sm text-gray-900 dark:text-gray-100">Active</span>
                                </label>
                                <p class="mt-3 text-sm/6 text-gray-600 dark:text-gray-300">Only active couriers will be available for shipping calculations</p>
                            </div>

                            {{-- Form Actions --}}
                            <div class="col-span-full">
                                <div class="flex items-center justify-end gap-x-3 border-t border-gray-900/10 dark:border-gray-100/10 pt-4">
                                    <button type="button" wire:click="cancel"
                                        class="text-sm font-semibold text-gray-900 dark:text-gray-100">Cancel</button>

                                    <button type="submit" wire:loading.attr="disabled"
                                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                        <span wire:loading.remove>Save</span>
                                        <span wire:loading>Saving...</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </x-admin-panel>
</div>
