@php($tabs = [
    'performance' => __('Performance'),
    'variance' => __('Variance'),
    'department' => __('By Department'),
    'cashflow' => __('Cash Flow'),
])
<div class="flex flex-wrap gap-2 mb-4">
    @foreach($tabs as $key => $label)
    <a href="{{ route('admin.budget-report.' . $key) }}"
       class="px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.budget-report.' . $key) ? 'bg-blue-500 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' }}">
        {{ $label }}
    </a>
    @endforeach
</div>
