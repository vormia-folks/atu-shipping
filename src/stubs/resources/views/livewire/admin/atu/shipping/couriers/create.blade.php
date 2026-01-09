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
};

?>

<div>
    <div class="mb-6">
        <a href="{{ route('admin.atu.shipping.couriers.index') }}" class="text-blue-600 hover:text-blue-900">
            ← Back to Couriers
        </a>
        <h2 class="text-2xl font-bold text-gray-900 mt-4">Create Courier</h2>
    </div>

    <div class="bg-white rounded-lg shadow-sm p-6">
        <form wire:submit="save">
            <div class="space-y-6">
                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="name" wire:model="name" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('name') border-red-500 @enderror"
                           placeholder="e.g., DHL Express">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Code -->
                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700 mb-2">
                        Code <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="code" wire:model="code" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('code') border-red-500 @enderror"
                           placeholder="e.g., dhl">
                    <p class="mt-1 text-sm text-gray-500">Unique identifier for this courier (lowercase, no spaces)</p>
                    @error('code')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        Description
                    </label>
                    <textarea id="description" wire:model="description" rows="3"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('description') border-red-500 @enderror"
                              placeholder="Optional description of the courier service"></textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Active Status -->
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" wire:model="is_active" 
                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Active</span>
                    </label>
                    <p class="mt-1 text-sm text-gray-500">Only active couriers will be available for shipping calculations</p>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <a href="{{ route('admin.atu.shipping.couriers.index') }}" 
                       class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Create Courier
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
