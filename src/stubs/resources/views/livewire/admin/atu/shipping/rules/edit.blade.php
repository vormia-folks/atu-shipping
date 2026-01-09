<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Traits\Vrm\Livewire\WithNotifications;

new class extends Component {
    use WithNotifications;

    public $rule_id;
    public $rule;
    public $fee;

    // Rule fields
    #[Validate('required|integer|exists:atu_shipping_couriers,id')]
    public $courier_id = null;

    #[Validate('required|string|max:255')]
    public $name = '';

    #[Validate('required|integer|min:0')]
    public $priority = 0;

    public $from_country = null;
    public $to_country = null;
    public $min_cart_subtotal = null;
    public $max_cart_subtotal = null;
    public $min_weight = null;
    public $max_weight = null;
    public $min_distance = null;
    public $max_distance = null;
    public $carrier_type = null;
    public $applies_per_item = false;
    public $tax_rate = null;
    public $currency = null;
    public $is_active = true;

    // Fee fields
    public $fee_type = 'flat';
    public $flat_fee = null;
    public $per_kg_fee = null;

    public function mount($id): void
    {
        $this->rule_id = $id;
        $this->rule = DB::table('atu_shipping_rules')->where('id', $this->rule_id)->first();
        $this->fee = DB::table('atu_shipping_fees')->where('rule_id', $this->rule_id)->first();

        if (!$this->rule) {
            $this->notifyError(__('Rule not found.'));
            return $this->redirect(route('admin.atu.shipping.rules.index'), navigate: true);
        }

        // Load rule data
        $this->courier_id = $this->rule->courier_id;
        $this->name = $this->rule->name;
        $this->priority = $this->rule->priority;
        $this->from_country = $this->rule->from_country;
        $this->to_country = $this->rule->to_country;
        $this->min_cart_subtotal = $this->rule->min_cart_subtotal;
        $this->max_cart_subtotal = $this->rule->max_cart_subtotal;
        $this->min_weight = $this->rule->min_weight;
        $this->max_weight = $this->rule->max_weight;
        $this->min_distance = $this->rule->min_distance;
        $this->max_distance = $this->rule->max_distance;
        $this->carrier_type = $this->rule->carrier_type;
        $this->applies_per_item = (bool) $this->rule->applies_per_item;
        $this->tax_rate = $this->rule->tax_rate;
        $this->currency = $this->rule->currency;
        $this->is_active = (bool) $this->rule->is_active;

        // Load fee data
        if ($this->fee) {
            $this->fee_type = $this->fee->fee_type;
            $this->flat_fee = $this->fee->flat_fee;
            $this->per_kg_fee = $this->fee->per_kg_fee;
        }
    }

    #[Computed]
    public function couriers()
    {
        return DB::table('atu_shipping_couriers')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function update(): void
    {
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

            // Update rule
            DB::table('atu_shipping_rules')
                ->where('id', $this->rule_id)
                ->update([
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
                    'updated_at' => now(),
                ]);

            // Update or create fee
            if ($this->fee) {
                DB::table('atu_shipping_fees')
                    ->where('rule_id', $this->rule_id)
                    ->update([
                        'fee_type' => $this->fee_type,
                        'flat_fee' => $this->fee_type === 'flat' ? $this->flat_fee : null,
                        'per_kg_fee' => $this->fee_type === 'per_kg' ? $this->per_kg_fee : null,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('atu_shipping_fees')->insert([
                    'rule_id' => $this->rule_id,
                    'fee_type' => $this->fee_type,
                    'flat_fee' => $this->fee_type === 'flat' ? $this->flat_fee : null,
                    'per_kg_fee' => $this->fee_type === 'per_kg' ? $this->per_kg_fee : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            $this->notifySuccess(__('Rule updated successfully!'));
            return $this->redirect(route('admin.atu.shipping.rules.index', ['courier' => $this->courier_id]), navigate: true);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->notifyError(__('Failed to update rule: ' . $e->getMessage()));
        }
    }
};

?>

<div>
    <div class="mb-6">
        <a href="{{ route('admin.atu.shipping.rules.index', ['courier' => $rule->courier_id ?? null]) }}" class="text-blue-600 hover:text-blue-900">
            ← Back to Rules
        </a>
        <h2 class="text-2xl font-bold text-gray-900 mt-4">Edit Shipping Rule</h2>
    </div>

    <div class="bg-white rounded-lg shadow-sm p-6">
        <form wire:submit="update">
            <div class="space-y-6">
                <!-- Basic Information -->
                <div class="border-b pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Courier -->
                        <div>
                            <label for="courier_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Courier <span class="text-red-500">*</span>
                            </label>
                            <select id="courier_id" wire:model="courier_id" 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('courier_id') border-red-500 @enderror">
                                @foreach($this->couriers as $courier)
                                    <option value="{{ $courier->id }}">{{ $courier->name }}</option>
                                @endforeach
                            </select>
                            @error('courier_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                Rule Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="name" wire:model="name" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('name') border-red-500 @enderror">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Priority -->
                        <div>
                            <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">
                                Priority <span class="text-red-500">*</span>
                            </label>
                            <input type="number" id="priority" wire:model="priority" min="0"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('priority') border-red-500 @enderror">
                            <p class="mt-1 text-sm text-gray-500">Lower numbers are evaluated first</p>
                            @error('priority')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Active Status -->
                        <div class="flex items-end">
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="is_active" 
                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Active</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Constraints -->
                <div class="border-b pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Constraints</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- From Country -->
                        <div>
                            <label for="from_country" class="block text-sm font-medium text-gray-700 mb-2">
                                From Country (ISO Code)
                            </label>
                            <input type="text" id="from_country" wire:model="from_country" maxlength="2"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="e.g., ZA">
                        </div>

                        <!-- To Country -->
                        <div>
                            <label for="to_country" class="block text-sm font-medium text-gray-700 mb-2">
                                To Country (ISO Code)
                            </label>
                            <input type="text" id="to_country" wire:model="to_country" maxlength="2"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="e.g., KE">
                        </div>

                        <!-- Min/Max Cart Subtotal -->
                        <div>
                            <label for="min_cart_subtotal" class="block text-sm font-medium text-gray-700 mb-2">
                                Min Cart Subtotal
                            </label>
                            <input type="number" id="min_cart_subtotal" wire:model="min_cart_subtotal" step="0.01" min="0"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label for="max_cart_subtotal" class="block text-sm font-medium text-gray-700 mb-2">
                                Max Cart Subtotal
                            </label>
                            <input type="number" id="max_cart_subtotal" wire:model="max_cart_subtotal" step="0.01" min="0"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <!-- Min/Max Weight -->
                        <div>
                            <label for="min_weight" class="block text-sm font-medium text-gray-700 mb-2">
                                Min Weight (kg)
                            </label>
                            <input type="number" id="min_weight" wire:model="min_weight" step="0.01" min="0"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label for="max_weight" class="block text-sm font-medium text-gray-700 mb-2">
                                Max Weight (kg)
                            </label>
                            <input type="number" id="max_weight" wire:model="max_weight" step="0.01" min="0"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>

                    <!-- Applies Per Item -->
                    <div class="mt-4">
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="applies_per_item" 
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm text-gray-700">Apply fee per item (instead of total cart weight)</span>
                        </label>
                    </div>
                </div>

                <!-- Fee Configuration -->
                <div class="border-b pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Fee Configuration</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Fee Type -->
                        <div>
                            <label for="fee_type" class="block text-sm font-medium text-gray-700 mb-2">
                                Fee Type <span class="text-red-500">*</span>
                            </label>
                            <select id="fee_type" wire:model.live="fee_type" 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('fee_type') border-red-500 @enderror">
                                <option value="flat">Flat Fee</option>
                                <option value="per_kg">Per Kilogram</option>
                            </select>
                            @error('fee_type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        @if($fee_type === 'flat')
                            <!-- Flat Fee -->
                            <div>
                                <label for="flat_fee" class="block text-sm font-medium text-gray-700 mb-2">
                                    Flat Fee <span class="text-red-500">*</span>
                                </label>
                                <input type="number" id="flat_fee" wire:model="flat_fee" step="0.01" min="0"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('flat_fee') border-red-500 @enderror">
                                @error('flat_fee')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @else
                            <!-- Per KG Fee -->
                            <div>
                                <label for="per_kg_fee" class="block text-sm font-medium text-gray-700 mb-2">
                                    Per Kilogram Fee <span class="text-red-500">*</span>
                                </label>
                                <input type="number" id="per_kg_fee" wire:model="per_kg_fee" step="0.01" min="0"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('per_kg_fee') border-red-500 @enderror">
                                @error('per_kg_fee')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Tax & Currency -->
                <div class="border-b pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Tax & Currency</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Tax Rate -->
                        <div>
                            <label for="tax_rate" class="block text-sm font-medium text-gray-700 mb-2">
                                Tax Rate (0-1)
                            </label>
                            <input type="number" id="tax_rate" wire:model="tax_rate" step="0.0001" min="0" max="1"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="e.g., 0.16 for 16%">
                        </div>

                        <!-- Currency -->
                        <div>
                            <label for="currency" class="block text-sm font-medium text-gray-700 mb-2">
                                Currency Code (ISO)
                            </label>
                            <input type="text" id="currency" wire:model="currency" maxlength="3"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="e.g., USD">
                            <p class="mt-1 text-sm text-gray-500">Leave empty to use base currency</p>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end space-x-3 pt-4">
                    <a href="{{ route('admin.atu.shipping.rules.index', ['courier' => $rule->courier_id ?? null]) }}" 
                       class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Update Rule
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
