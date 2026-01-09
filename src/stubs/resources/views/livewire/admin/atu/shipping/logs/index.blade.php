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
        $query = DB::table('atu_shipping_logs')->leftJoin('atu_shipping_couriers', 'atu_shipping_logs.courier_id', '=', 'atu_shipping_couriers.id')->leftJoin('atu_shipping_rules', 'atu_shipping_logs.rule_id', '=', 'atu_shipping_rules.id')->select('atu_shipping_logs.*', 'atu_shipping_couriers.name as courier_name', 'atu_shipping_rules.name as rule_name');

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
        return DB::table('atu_shipping_couriers')->orderBy('name')->get();
    }
};

?>

<div>
	<x-admin-panel>
		<x-slot name="header">{{ __('Shipping Logs') }}</x-slot>
		<x-slot name="desc">
			{{ __('View shipping calculation logs from checkout and manual calculations') }}
		</x-slot>

		{{-- Search & Filter --}}
		<div class="my-4">
			<div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
				<div class="px-4 py-5 sm:p-6">
					<h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Search & Filter data</h3>
					<form class="mt-4 space-y-4">
						<div class="grid grid-cols-1 md:grid-cols-4 gap-4">
							<div>
								<label class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Search</label>
								<input type="text" wire:model.live.debounce.300ms="search"
									class="block w-full rounded-md bg-white dark:bg-gray-700 px-3 py-1.5 text-base text-gray-900 dark:text-gray-100 outline-1 -outline-offset-1 outline-gray-300 dark:outline-gray-600 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6"
									placeholder="Search by courier, rule, or order ID..." />
							</div>
							<div>
								<label class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Courier</label>
								<select wire:model.live="courierFilter"
									class="block w-full rounded-md bg-white dark:bg-gray-700 px-3 py-1.5 text-base text-gray-900 dark:text-gray-100 outline-1 -outline-offset-1 outline-gray-300 dark:outline-gray-600 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6">
									<option value="">All Couriers</option>
									@foreach ($this->couriers as $courier)
										<option value="{{ $courier->id }}">{{ $courier->name }}</option>
									@endforeach
								</select>
							</div>
							<div>
								<label class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Date From</label>
								<input type="date" wire:model.live="dateFrom"
									class="block w-full rounded-md bg-white dark:bg-gray-700 px-3 py-1.5 text-base text-gray-900 dark:text-gray-100 outline-1 -outline-offset-1 outline-gray-300 dark:outline-gray-600 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
							</div>
							<div>
								<label class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Date To</label>
								<input type="date" wire:model.live="dateTo"
									class="block w-full rounded-md bg-white dark:bg-gray-700 px-3 py-1.5 text-base text-gray-900 dark:text-gray-100 outline-1 -outline-offset-1 outline-gray-300 dark:outline-gray-600 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
							</div>
						</div>
						<div class="flex items-center gap-4">
							<div>
								<label class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Per Page</label>
								<select wire:model.live="perPage"
									class="block w-full md:w-auto rounded-md bg-white dark:bg-gray-700 px-3 py-1.5 text-base text-gray-900 dark:text-gray-100 outline-1 -outline-offset-1 outline-gray-300 dark:outline-gray-600 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6">
									<option value="25">25</option>
									<option value="50">50</option>
									<option value="100">100</option>
								</select>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>

		{{-- Display notifications --}}
		{!! $this->renderNotification() !!}

		{{-- Logs Table --}}
		<div class="overflow-hidden shadow-sm ring-1 ring-black/5 dark:ring-white/10 sm:rounded-lg mt-2">
			<div class="overflow-x-auto">
				<table class="min-w-full divide-y divide-gray-300 dark:divide-gray-600">
					<thead class="bg-gray-50 dark:bg-gray-700">
						<tr>
							<th scope="col"
								class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 dark:text-gray-100 sm:pl-3">Date</th>
							<th scope="col"
								class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 dark:text-gray-100 sm:pl-3">Courier</th>
							<th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Rule</th>
							<th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Route
							</th>
							<th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Weight
							</th>
							<th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Subtotal
							</th>
							<th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Fee</th>
							<th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Tax</th>
							<th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Total
							</th>
							<th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Order ID
							</th>
						</tr>
					</thead>
					<tbody class="bg-white dark:bg-gray-800">
						@if ($this->logs->isNotEmpty())
							@foreach ($this->logs as $log)
								<tr class="even:bg-gray-50 dark:even:bg-gray-800/50">
									<td class="py-4 pr-3 pl-4 text-sm font-medium whitespace-nowrap text-gray-900 dark:text-gray-100 sm:pl-3">
										{{ $log->created_at->format('Y-m-d H:i') }}
									</td>
									<td class="py-4 pr-3 pl-4 text-sm font-medium whitespace-nowrap text-gray-900 dark:text-gray-100 sm:pl-3">
										{{ $log->courier_name ?? 'N/A' }}
									</td>
									<td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
										{{ Str::limit($log->rule_name ?? 'N/A', 30) }}
									</td>
									<td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
										{{ $log->from_country ?? 'N/A' }} → {{ $log->to_country ?? 'N/A' }}
									</td>
									<td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
										{{ number_format($log->total_weight, 2) }} kg
									</td>
									<td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
										{{ $log->currency }} {{ number_format($log->cart_subtotal, 2) }}
									</td>
									<td class="px-3 py-4 text-sm whitespace-nowrap text-gray-900 dark:text-gray-100">
										{{ $log->currency }} {{ number_format($log->shipping_fee, 2) }}
									</td>
									<td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
										{{ $log->currency }} {{ number_format($log->shipping_tax, 2) }}
									</td>
									<td class="px-3 py-4 text-sm whitespace-nowrap text-green-600 dark:text-green-400">
										{{ $log->currency }} {{ number_format($log->shipping_total, 2) }}
									</td>
									<td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
										{{ $log->order_id ?? 'N/A' }}
									</td>
								</tr>
							@endforeach
						@else
							<tr class="even:bg-gray-50 dark:even:bg-gray-800/50">
								<td colspan="10"
									class="py-4 pr-3 pl-4 text-sm font-medium whitespace-nowrap text-gray-900 dark:text-gray-100 sm:pl-3 text-center">
									<span class="text-gray-500 dark:text-gray-400 text-2xl font-bold">No logs found</span>
								</td>
							</tr>
						@endif
					</tbody>
				</table>
			</div>
		</div>

		{{-- Pagination --}}
		<div class="mt-8">
			@if ($this->logs->hasPages())
				<div class="p-2">
					{{ $this->logs->links() }}
				</div>
			@endif
		</div>
	</x-admin-panel>
</div>
