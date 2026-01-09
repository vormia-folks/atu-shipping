<?php

use Livewire\Attributes\On;
use Livewire\WithPagination;
use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Traits\Vrm\Livewire\WithNotifications;

new class extends Component {
    use WithPagination;
    use WithNotifications;

    public $search = '';
    public $perPage = 10;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedPerPage()
    {
        $this->resetPage();
    }

    #[Computed]
    public function couriers()
    {
        $query = DB::table('atu_shipping_couriers');

        // Apply search filter
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        // Order by created_at desc by default
        $query->orderBy('created_at', 'desc');

        return $query->paginate($this->perPage);
    }

    public function toggleActive($courierId)
    {
        try {
            $courier = DB::table('atu_shipping_couriers')->where('id', $courierId)->first();

            if (!$courier) {
                $this->notifyError(__('Courier not found.'));
                return;
            }

            DB::table('atu_shipping_couriers')
                ->where('id', $courierId)
                ->update(['is_active' => !$courier->is_active]);

            $this->notifySuccess(__('Courier status updated successfully!'));
        } catch (\Exception $e) {
            $this->notifyError(__('Failed to update courier status: ' . $e->getMessage()));
        }
    }

    public function delete($courierId)
    {
        try {
            $courier = DB::table('atu_shipping_couriers')->where('id', $courierId)->first();

            if (!$courier) {
                $this->notifyError(__('Courier not found.'));
                return;
            }

            // Check if courier has rules
            $hasRules = DB::table('atu_shipping_rules')
                ->where('courier_id', $courierId)
                ->exists();

            if ($hasRules) {
                $this->notifyError(__('Cannot delete courier with existing rules. Please delete rules first.'));
                return;
            }

            DB::table('atu_shipping_couriers')->where('id', $courierId)->delete();

            $this->notifySuccess(__('Courier deleted successfully!'));
        } catch (\Exception $e) {
            $this->notifyError(__('Failed to delete courier: ' . $e->getMessage()));
        }
    }
};

?>

<div>
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Shipping Couriers</h2>
        <a href="{{ route('admin.atu.shipping.couriers.create') }}" class="btn btn-primary">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Add Courier
        </a>
    </div>

    <!-- Search and Filters -->
    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                <input type="text" wire:model.live.debounce.300ms="search" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="Search by name, code, or description...">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Per Page</label>
                <select wire:model.live="perPage" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Couriers Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rules</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($this->couriers as $courier)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $courier->name }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">{{ $courier->code }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-500">{{ Str::limit($courier->description ?? 'N/A', 50) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button wire:click="toggleActive({{ $courier->id }})" 
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                               {{ $courier->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $courier->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $ruleCount = DB::table('atu_shipping_rules')->where('courier_id', $courier->id)->count();
                                @endphp
                                <span class="text-sm text-gray-500">{{ $ruleCount }} rule(s)</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <a href="{{ route('admin.atu.shipping.rules.index', ['courier' => $courier->id]) }}" 
                                       class="text-blue-600 hover:text-blue-900">Rules</a>
                                    <a href="{{ route('admin.atu.shipping.couriers.edit', $courier->id) }}" 
                                       class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                    <button wire:click="delete({{ $courier->id }})" 
                                            wire:confirm="Are you sure you want to delete this courier?"
                                            class="text-red-600 hover:text-red-900">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                No couriers found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $this->couriers->links() }}
        </div>
    </div>
</div>
