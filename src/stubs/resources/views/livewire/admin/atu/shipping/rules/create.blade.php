<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Traits\Vrm\Livewire\WithNotifications;

new class extends Component {
    use WithNotifications;

    public $courierFilter = null;

    // Rule fields
    #[Validate('required|integer|exists:atu_shipping_couriers,id')]
    public $courier_id = null;

    #[Validate('required|string|max:255')]
    public $name = '';

    #[Validate('required|integer|min:0')]
    public $priority = 0;

    #[Validate('nullable|string|size:2')]
    public $from_country = null;

    #[Validate('nullable|string|size:2')]
    public $to_country = null;

    #[Validate('nullable|numeric|min:0')]
    public $min_cart_subtotal = null;

    #[Validate('nullable|numeric|min:0')]
    public $max_cart_subtotal = null;

    #[Validate('nullable|numeric|min:0')]
    public $min_weight = null;

    #[Validate('nullable|numeric|min:0')]
    public $max_weight = null;

    #[Validate('nullable|numeric|min:0')]
    public $min_distance = null;

    #[Validate('nullable|numeric|min:0')]
    public $max_distance = null;

    #[Validate('nullable|string|max:50')]
    public $carrier_type = null;

    #[Validate('required|boolean')]
    public $applies_per_item = false;

    #[Validate('nullable|numeric|min:0|max:1')]
    public $tax_rate = null;

    #[Validate('nullable|string|size:3')]
    public $currency = null;

    #[Validate('required|boolean')]
    public $is_active = true;

    // Fee fields
    #[Validate('required|in:flat,per_kg')]
    public $fee_type = 'flat';

    #[Validate('nullable|required_if:fee_type,flat|numeric|min:0')]
    public $flat_fee = null;

    #[Validate('nullable|required_if:fee_type,per_kg|numeric|min:0')]
    public $per_kg_fee = null;

    public function mount($courier = null)
    {
        $this->courierFilter = $courier;
        $this->courier_id = $courier;
    }

    #[Computed]
    public function couriers()
    {
        return DB::table('atu_shipping_couriers')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function save()
    {
        // Custom validation
        $this->validate([
            'courier_id' => 'required|integer|exists:atu_shipping_couriers,id',
            'name' => 'required|string|max:255',
            'priority' => 'required|integer|min:0',
            'fee_type' => 'required|in:flat,per_kg',
            'flat_fee' => $this->fee_type === 'flat' ? 'required|numeric|min:0' : 'nullable',
            'per_kg_fee' => $this->fee_type === 'per_kg' ? 'required|numeric|min:0' : 'nullable',
        ]);

        try {
            DB::beginTransaction();

            // Create rule
            $ruleId = DB::table('atu_shipping_rules')->insertGetId([
                'courier_id' => $this->courier_id,
                'name' => $this->name,
                'priority' => $this->priority,
                'from_country' => $this->from_country ?: null,
                'to_country' => $this->to_country ?: null,
                'min_cart_subtotal' => $this->min_cart_subtotal,
                'max_cart_subtotal' => $this->max_cart_subtotal,
                'min_weight' => $this->min_weight,
                'max_weight' => $this->max_weight,
                'min_distance' => $this->min_distance,
                'max_distance' => $this->max_distance,
                'carrier_type' => $this->carrier_type ?: null,
                'applies_per_item' => $this->applies_per_item,
                'tax_rate' => $this->tax_rate,
                'currency' => $this->currency ?: null,
                'is_active' => $this->is_active,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create fee
            DB::table('atu_shipping_fees')->insert([
                'rule_id' => $ruleId,
                'fee_type' => $this->fee_type,
                'flat_fee' => $this->fee_type === 'flat' ? $this->flat_fee : null,
                'per_kg_fee' => $this->fee_type === 'per_kg' ? $this->per_kg_fee : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            $this->notifySuccess(__('Rule created successfully!'));
            return $this->redirect(route('admin.atu.shipping.rules.index', ['courier' => $this->courier_id]), navigate: true);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->notifyError(__('Failed to create rule: ' . $e->getMessage()));
        }
    }

    public function cancel(): void
    {
        $this->notifyInfo(__('Creation cancelled!'));
    }
};

?>

<div>
    <x-admin-panel>
        <x-slot name="header">{{ __('Add New Shipping Rule') }}</x-slot>
        <x-slot name="desc">
            {{ __('Add a new shipping rule to the system.') }}
        </x-slot>
        <x-slot name="button">
            <a href="{{ route('admin.atu.shipping.rules.index', ['courier' => $courierFilter]) }}"
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
                    {{-- Basic Information --}}
                    <div class="grid grid-cols-1 gap-x-8 gap-y-10 pb-12 md:grid-cols-3">
                        <div>
                            <h2 class="text-base/7 font-semibold text-gray-900 dark:text-gray-100">Basic Information</h2>
                            <p class="mt-1 text-sm/6 text-gray-600 dark:text-gray-300">
                                Enter the basic information for the shipping rule.
                            </p>
                        </div>

                        <div class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6 md:col-span-2">
                            {{-- Courier --}}
                            <div class="col-span-full sm:col-span-3">
                                <label for="courier_id" class="block text-sm/6 font-medium text-gray-900 dark:text-gray-100 required">Courier</label>
                                <div class="mt-2">
                                    <select id="courier_id" wire:model="courier_id"
                                        class="block w-full rounded-md bg-white dark:bg-gray-700 px-3 py-1.5 text-base text-gray-900 dark:text-gray-100 outline-1 -outline-offset-1 outline-gray-300 dark:outline-gray-600 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6">
                                        <option value="">Select Courier</option>
                                        @foreach($this->couriers as $courier)
                                            <option value="{{ $courier->id }}">{{ $courier->name }}</option>
                                        @endforeach
                                    </select>
                                    <span class="text-red-500 text-sm italic"> {{ $errors->first('courier_id') }}</span>
                                </div>
                            </div>

                            {{-- Name --}}
                            <div class="col-span-full sm:col-span-3">
                                <label for="name" class="block text-sm/6 font-medium text-gray-900 dark:text-gray-100 required">Rule Name</label>
                                <div class="mt-2">
                                    <div class="flex items-center rounded-md bg-white dark:bg-gray-700 pl-3 outline-1 -outline-offset-1 outline-gray-300 dark:outline-gray-600 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-indigo-600">
                                        <input type="text" id="name" wire:model="name" placeholder="e.g., Standard Shipping ZA to KE"
                                            class="block min-w-0 grow py-1.5 pr-3 pl-1 text-base text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:outline-none sm:text-sm/6" />
                                    </div>
                                    <span class="text-red-500 text-sm italic"> {{ $errors->first('name') }}</span>
                                </div>
                            </div>

                            {{-- Priority --}}
                            <div class="col-span-full sm:col-span-3">
                                <label for="priority" class="block text-sm/6 font-medium text-gray-900 dark:text-gray-100 required">Priority</label>
                                <div class="mt-2">
                                    <div class="flex items-center rounded-md bg-white dark:bg-gray-700 pl-3 outline-1 -outline-offset-1 outline-gray-300 dark:outline-gray-600 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-indigo-600">
                                        <input type="number" id="priority" wire:model="priority" min="0"
                                            class="block min-w-0 grow py-1.5 pr-3 pl-1 text-base text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:outline-none sm:text-sm/6" />
                                    </div>
                                    <span class="text-red-500 text-sm italic"> {{ $errors->first('priority') }}</span>
                                </div>
                                <p class="mt-3 text-sm/6 text-gray-600 dark:text-gray-300">Lower numbers are evaluated first</p>
                            </div>

                            {{-- Active Status --}}
                            <div class="col-span-full sm:col-span-3 flex items-end">
                                <label class="flex items-center">
                                    <input type="checkbox" wire:model="is_active"
                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600">
                                    <span class="ml-2 text-sm text-gray-900 dark:text-gray-100">Active</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Constraints --}}
                    <div class="grid grid-cols-1 gap-x-8 gap-y-10 pb-12 md:grid-cols-3">
                        <div>
                            <h2 class="text-base/7 font-semibold text-gray-900 dark:text-gray-100">Constraints</h2>
                            <p class="mt-1 text-sm/6 text-gray-600 dark:text-gray-300">
                                Define the constraints for when this rule applies.
                            </p>
                        </div>

                        <div class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6 md:col-span-2">
                            {{-- From Country --}}
                            <div class="col-span-full sm:col-span-3">
                                <label for="from_country" class="block text-sm/6 font-medium text-gray-900 dark:text-gray-100">From Country (ISO Code)</label>
                                <div class="mt-2">
                                    <div class="flex items-center rounded-md bg-white dark:bg-gray-700 pl-3 outline-1 -outline-offset-1 outline-gray-300 dark:outline-gray-600 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-indigo-600">
                                        <input type="text" id="from_country" wire:model="from_country" maxlength="2" placeholder="e.g., ZA"
                                            class="block min-w-0 grow py-1.5 pr-3 pl-1 text-base text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:outline-none sm:text-sm/6" />
                                    </div>
                                    <span class="text-red-500 text-sm italic"> {{ $errors->first('from_country') }}</span>
                                </div>
                            </div>

                            {{-- To Country --}}
                            <div class="col-span-full sm:col-span-3">
                                <label for="to_country" class="block text-sm/6 font-medium text-gray-900 dark:text-gray-100">To Country (ISO Code)</label>
                                <div class="mt-2">
                                    <div class="flex items-center rounded-md bg-white dark:bg-gray-700 pl-3 outline-1 -outline-offset-1 outline-gray-300 dark:outline-gray-600 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-indigo-600">
                                        <input type="text" id="to_country" wire:model="to_country" maxlength="2" placeholder="e.g., KE"
                                            class="block min-w-0 grow py-1.5 pr-3 pl-1 text-base text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:outline-none sm:text-sm/6" />
                                    </div>
                                    <span class="text-red-500 text-sm italic"> {{ $errors->first('to_country') }}</span>
                                </div>
                            </div>

                            {{-- Min Cart Subtotal --}}
                            <div class="col-span-full sm:col-span-3">
                                <label for="min_cart_subtotal" class="block text-sm/6 font-medium text-gray-900 dark:text-gray-100">Min Cart Subtotal</label>
                                <div class="mt-2">
                                    <div class="flex items-center rounded-md bg-white dark:bg-gray-700 pl-3 outline-1 -outline-offset-1 outline-gray-300 dark:outline-gray-600 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-indigo-600">
                                        <input type="number" id="min_cart_subtotal" wire:model="min_cart_subtotal" step="0.01" min="0"
                                            class="block min-w-0 grow py-1.5 pr-3 pl-1 text-base text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:outline-none sm:text-sm/6" />
                                    </div>
                                </div>
                            </div>

                            {{-- Max Cart Subtotal --}}
                            <div class="col-span-full sm:col-span-3">
                                <label for="max_cart_subtotal" class="block text-sm/6 font-medium text-gray-900 dark:text-gray-100">Max Cart Subtotal</label>
                                <div class="mt-2">
                                    <div class="flex items-center rounded-md bg-white dark:bg-gray-700 pl-3 outline-1 -outline-offset-1 outline-gray-300 dark:outline-gray-600 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-indigo-600">
                                        <input type="number" id="max_cart_subtotal" wire:model="max_cart_subtotal" step="0.01" min="0"
                                            class="block min-w-0 grow py-1.5 pr-3 pl-1 text-base text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:outline-none sm:text-sm/6" />
                                    </div>
                                </div>
                            </div>

                            {{-- Min Weight --}}
                            <div class="col-span-full sm:col-span-3">
                                <label for="min_weight" class="block text-sm/6 font-medium text-gray-900 dark:text-gray-100">Min Weight (kg)</label>
                                <div class="mt-2">
                                    <div class="flex items-center rounded-md bg-white dark:bg-gray-700 pl-3 outline-1 -outline-offset-1 outline-gray-300 dark:outline-gray-600 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-indigo-600">
                                        <input type="number" id="min_weight" wire:model="min_weight" step="0.01" min="0"
                                            class="block min-w-0 grow py-1.5 pr-3 pl-1 text-base text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:outline-none sm:text-sm/6" />
                                    </div>
                                </div>
                            </div>

                            {{-- Max Weight --}}
                            <div class="col-span-full sm:col-span-3">
                                <label for="max_weight" class="block text-sm/6 font-medium text-gray-900 dark:text-gray-100">Max Weight (kg)</label>
                                <div class="mt-2">
                                    <div class="flex items-center rounded-md bg-white dark:bg-gray-700 pl-3 outline-1 -outline-offset-1 outline-gray-300 dark:outline-gray-600 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-indigo-600">
                                        <input type="number" id="max_weight" wire:model="max_weight" step="0.01" min="0"
                                            class="block min-w-0 grow py-1.5 pr-3 pl-1 text-base text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:outline-none sm:text-sm/6" />
                                    </div>
                                </div>
                            </div>

                            {{-- Applies Per Item --}}
                            <div class="col-span-full">
                                <label class="flex items-center">
                                    <input type="checkbox" wire:model="applies_per_item"
                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600">
                                    <span class="ml-2 text-sm text-gray-900 dark:text-gray-100">Apply fee per item (instead of total cart weight)</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Fee Configuration --}}
                    <div class="grid grid-cols-1 gap-x-8 gap-y-10 pb-12 md:grid-cols-3">
                        <div>
                            <h2 class="text-base/7 font-semibold text-gray-900 dark:text-gray-100">Fee Configuration</h2>
                            <p class="mt-1 text-sm/6 text-gray-600 dark:text-gray-300">
                                Configure how the shipping fee is calculated.
                            </p>
                        </div>

                        <div class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6 md:col-span-2">
                            {{-- Fee Type --}}
                            <div class="col-span-full sm:col-span-3">
                                <label for="fee_type" class="block text-sm/6 font-medium text-gray-900 dark:text-gray-100 required">Fee Type</label>
                                <div class="mt-2">
                                    <select id="fee_type" wire:model.live="fee_type"
                                        class="block w-full rounded-md bg-white dark:bg-gray-700 px-3 py-1.5 text-base text-gray-900 dark:text-gray-100 outline-1 -outline-offset-1 outline-gray-300 dark:outline-gray-600 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6">
                                        <option value="flat">Flat Fee</option>
                                        <option value="per_kg">Per Kilogram</option>
                                    </select>
                                    <span class="text-red-500 text-sm italic"> {{ $errors->first('fee_type') }}</span>
                                </div>
                            </div>

                            @if($fee_type === 'flat')
                                {{-- Flat Fee --}}
                                <div class="col-span-full sm:col-span-3">
                                    <label for="flat_fee" class="block text-sm/6 font-medium text-gray-900 dark:text-gray-100 required">Flat Fee</label>
                                    <div class="mt-2">
                                        <div class="flex items-center rounded-md bg-white dark:bg-gray-700 pl-3 outline-1 -outline-offset-1 outline-gray-300 dark:outline-gray-600 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-indigo-600">
                                            <input type="number" id="flat_fee" wire:model="flat_fee" step="0.01" min="0"
                                                class="block min-w-0 grow py-1.5 pr-3 pl-1 text-base text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:outline-none sm:text-sm/6" />
                                        </div>
                                        <span class="text-red-500 text-sm italic"> {{ $errors->first('flat_fee') }}</span>
                                    </div>
                                </div>
                            @else
                                {{-- Per KG Fee --}}
                                <div class="col-span-full sm:col-span-3">
                                    <label for="per_kg_fee" class="block text-sm/6 font-medium text-gray-900 dark:text-gray-100 required">Per Kilogram Fee</label>
                                    <div class="mt-2">
                                        <div class="flex items-center rounded-md bg-white dark:bg-gray-700 pl-3 outline-1 -outline-offset-1 outline-gray-300 dark:outline-gray-600 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-indigo-600">
                                            <input type="number" id="per_kg_fee" wire:model="per_kg_fee" step="0.01" min="0"
                                                class="block min-w-0 grow py-1.5 pr-3 pl-1 text-base text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:outline-none sm:text-sm/6" />
                                        </div>
                                        <span class="text-red-500 text-sm italic"> {{ $errors->first('per_kg_fee') }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Tax & Currency --}}
                    <div class="grid grid-cols-1 gap-x-8 gap-y-10 pb-12 md:grid-cols-3">
                        <div>
                            <h2 class="text-base/7 font-semibold text-gray-900 dark:text-gray-100">Tax & Currency</h2>
                            <p class="mt-1 text-sm/6 text-gray-600 dark:text-gray-300">
                                Configure tax rate and currency settings.
                            </p>
                        </div>

                        <div class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6 md:col-span-2">
                            {{-- Tax Rate --}}
                            <div class="col-span-full sm:col-span-3">
                                <label for="tax_rate" class="block text-sm/6 font-medium text-gray-900 dark:text-gray-100">Tax Rate (0-1)</label>
                                <div class="mt-2">
                                    <div class="flex items-center rounded-md bg-white dark:bg-gray-700 pl-3 outline-1 -outline-offset-1 outline-gray-300 dark:outline-gray-600 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-indigo-600">
                                        <input type="number" id="tax_rate" wire:model="tax_rate" step="0.0001" min="0" max="1" placeholder="e.g., 0.16 for 16%"
                                            class="block min-w-0 grow py-1.5 pr-3 pl-1 text-base text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:outline-none sm:text-sm/6" />
                                    </div>
                                </div>
                            </div>

                            {{-- Currency --}}
                            <div class="col-span-full sm:col-span-3">
                                <label for="currency" class="block text-sm/6 font-medium text-gray-900 dark:text-gray-100">Currency Code (ISO)</label>
                                <div class="mt-2">
                                    <div class="flex items-center rounded-md bg-white dark:bg-gray-700 pl-3 outline-1 -outline-offset-1 outline-gray-300 dark:outline-gray-600 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-indigo-600">
                                        <input type="text" id="currency" wire:model="currency" maxlength="3" placeholder="e.g., USD"
                                            class="block min-w-0 grow py-1.5 pr-3 pl-1 text-base text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:outline-none sm:text-sm/6" />
                                    </div>
                                </div>
                                <p class="mt-3 text-sm/6 text-gray-600 dark:text-gray-300">Leave empty to use base currency</p>
                            </div>
                        </div>
                    </div>

                    {{-- Form Actions --}}
                    <div class="flex items-center justify-end gap-x-3 border-t border-gray-900/10 dark:border-gray-100/10 pt-4">
                        <button type="button" wire:click="cancel"
                            class="text-sm font-semibold text-gray-900 dark:text-gray-100">Cancel</button>

                        <button type="submit" wire:loading.attr="disabled"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                            <span wire:loading.remove>Create Rule</span>
                            <span wire:loading>Creating...</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </x-admin-panel>
</div>
