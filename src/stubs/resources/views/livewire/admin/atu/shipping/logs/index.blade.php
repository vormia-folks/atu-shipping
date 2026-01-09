<?php

use Livewire\WithPagination;
use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Traits\Vrm\Livewire\WithNotifications;

new class extends Component {
    use WithPagination;
    use WithNotifications;

    public $search = '';
    public $perPage = 25;
    public $courierFilter = null;
    public $dateFrom = null;
    public $dateTo = null;

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

    public function updatedDateFrom()
    {
        $this->resetPage();
    }

    public function updatedDateTo()
    {
        $this->resetPage();
    }

    #[Computed]
    public function logs()
    {
        $query = DB::table('atu_shipping_logs')
            ->leftJoin('atu_shipping_couriers', 'atu_shipping_logs.courier_id', '=', 'atu_shipping_couriers.id')
            ->leftJoin('atu_shipping_rules', 'atu_shipping_logs.rule_id', '=', 'atu_shipping_rules.id')
            ->select(
                'atu_shipping_logs.*',
                'atu_shipping_couriers.name as courier_name',
                'atu_shipping_rules.name as rule_name'
            );

        // Apply courier filter
        if ($this->courierFilter) {
            $query->where('atu_shipping_logs.courier_id', $this->courierFilter);
        }

        // Apply date filters
        if ($this->dateFrom) {
            $query->whereDate('atu_shipping_logs.created_at', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('atu_shipping_logs.created_at', '<=', $this->dateTo);
        }

        // Apply search filter
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('atu_shipping_couriers.name', 'like', '%' . $this->search . '%')
                  ->orWhere('atu_shipping_rules.name', 'like', '%' . $this->search . '%')
                  ->orWhere('atu_shipping_logs.order_id', 'like', '%' . $this->search . '%');
            });
        }

        // Order by created_at desc
        $query->orderBy('atu_shipping_logs.created_at', 'desc');

        return $query->paginate($this->perPage);
    }

    #[Computed]
    public function couriers()
    {
        return DB::table('atu_shipping_couriers')
            ->orderBy('name')
            ->get();
    }
};

?>

<div>
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Shipping Logs</h2>
        <p class="text-sm text-gray-500 mt-1">View shipping calculation logs from checkout and manual calculations</p>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                <input type="text" wire:model.live.debounce.300ms="search" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="Search by courier, rule, or order ID...">
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
                <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                <input type="date" wire:model.live="dateFrom" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                <input type="date" wire:model.live="dateTo" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
        </div>
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Per Page</label>
            <select wire:model.live="perPage" 
                    class="w-full md:w-auto px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Courier</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rule</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Route</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fee</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tax</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($this->logs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $log->created_at->format('Y-m-d H:i') }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $log->courier_name ?? 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-500">{{ Str::limit($log->rule_name ?? 'N/A', 30) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">
                                    {{ $log->from_country ?? 'N/A' }} → {{ $log->to_country ?? 'N/A' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">{{ number_format($log->total_weight, 2) }} kg</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">{{ $log->currency }} {{ number_format($log->cart_subtotal, 2) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $log->currency }} {{ number_format($log->shipping_fee, 2) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">{{ $log->currency }} {{ number_format($log->shipping_tax, 2) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-green-600">{{ $log->currency }} {{ number_format($log->shipping_total, 2) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">{{ $log->order_id ?? 'N/A' }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-4 text-center text-sm text-gray-500">
                                No logs found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $this->logs->links() }}
        </div>
    </div>
</div>
