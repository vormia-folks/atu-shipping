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
    public $courierFilter = null;

    public function mount($courier = null)
    {
        $this->courierFilter = $courier;
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedPerPage()
    {
        $this->resetPage();
    }

    public function updatedCourierFilter()
    {
        $this->resetPage();
    }

    #[Computed]
    public function rules()
    {
        $query = DB::table('atu_shipping_rules')
            ->join('atu_shipping_couriers', 'atu_shipping_rules.courier_id', '=', 'atu_shipping_couriers.id')
            ->select('atu_shipping_rules.*', 'atu_shipping_couriers.name as courier_name', 'atu_shipping_couriers.code as courier_code');

        // Apply courier filter
        if ($this->courierFilter) {
            $query->where('atu_shipping_rules.courier_id', $this->courierFilter);
        }

        // Apply search filter
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('atu_shipping_rules.name', 'like', '%' . $this->search . '%')
                  ->orWhere('atu_shipping_couriers.name', 'like', '%' . $this->search . '%');
            });
        }

        // Order by priority ascending, then created_at desc
        $query->orderBy('atu_shipping_rules.priority', 'asc')
              ->orderBy('atu_shipping_rules.created_at', 'desc');

        return $query->paginate($this->perPage);
    }

    #[Computed]
    public function couriers()
    {
        return DB::table('atu_shipping_couriers')
            ->orderBy('name')
            ->get();
    }

    public function toggleActive($ruleId)
    {
        try {
            $rule = DB::table('atu_shipping_rules')->where('id', $ruleId)->first();

            if (!$rule) {
                $this->notifyError(__('Rule not found.'));
                return;
            }

            DB::table('atu_shipping_rules')
                ->where('id', $ruleId)
                ->update(['is_active' => !$rule->is_active]);

            $this->notifySuccess(__('Rule status updated successfully!'));
        } catch (\Exception $e) {
            $this->notifyError(__('Failed to update rule status: ' . $e->getMessage()));
        }
    }

    public function delete($ruleId)
    {
        try {
            DB::table('atu_shipping_rules')->where('id', $ruleId)->delete();
            $this->notifySuccess(__('Rule deleted successfully!'));
        } catch (\Exception $e) {
            $this->notifyError(__('Failed to delete rule: ' . $e->getMessage()));
        }
    }
};

?>

<div>
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Shipping Rules</h2>
        <a href="{{ route('admin.atu.shipping.rules.create', ['courier' => $courierFilter]) }}" class="btn btn-primary">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Add Rule
        </a>
    </div>

    <!-- Search and Filters -->
    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                <input type="text" wire:model.live.debounce.300ms="search" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="Search by name or courier...">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Courier</label>
                <select wire:model.live="courierFilter" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Couriers</option>
                    @foreach($this->couriers as $courier)
                        <option value="{{ $courier->id }}">{{ $courier->name }}</option>
                    @endforeach
                </select>
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

    <!-- Rules Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Courier</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Constraints</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($this->rules as $rule)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $rule->priority }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $rule->name }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">{{ $rule->courier_name }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-500 space-y-1">
                                    @if($rule->from_country || $rule->to_country)
                                        <div>📍 {{ $rule->from_country ?? 'Any' }} → {{ $rule->to_country ?? 'Any' }}</div>
                                    @endif
                                    @if($rule->min_weight || $rule->max_weight)
                                        <div>⚖️ {{ $rule->min_weight ?? '0' }} - {{ $rule->max_weight ?? '∞' }} kg</div>
                                    @endif
                                    @if($rule->min_cart_subtotal || $rule->max_cart_subtotal)
                                        <div>💰 {{ number_format($rule->min_cart_subtotal ?? 0, 2) }} - {{ $rule->max_cart_subtotal ? number_format($rule->max_cart_subtotal, 2) : '∞' }}</div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button wire:click="toggleActive({{ $rule->id }})" 
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                               {{ $rule->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $rule->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <a href="{{ route('admin.atu.shipping.rules.edit', $rule->id) }}" 
                                       class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                    <button wire:click="delete({{ $rule->id }})" 
                                            wire:confirm="Are you sure you want to delete this rule?"
                                            class="text-red-600 hover:text-red-900">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                No rules found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $this->rules->links() }}
        </div>
    </div>
</div>
