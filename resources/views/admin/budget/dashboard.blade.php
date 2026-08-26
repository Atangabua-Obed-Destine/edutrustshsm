@extends('layouts.admin')

@section('title', __('Budget Dashboard'))
@section('breadcrumb', __('Budgets > Dashboard'))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'CFA')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-700">{{ __('Budget Dashboard') }}</h3>
        <a href="{{ route('admin.budget.index') }}" class="text-sm text-blue-600 hover:underline">{{ __('All Budgets →') }}</a>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs text-gray-500">{{ __('Total Budget') }}</p><p class="text-lg font-bold text-gray-800">{{ number_format($kpis['total'], 0) }}</p></div>
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs text-gray-500">{{ __('Allocated') }}</p><p class="text-lg font-bold text-blue-700">{{ number_format($kpis['allocated'], 0) }}</p></div>
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs text-gray-500">{{ __('Spent') }}</p><p class="text-lg font-bold text-red-700">{{ number_format($kpis['spent'], 0) }}</p></div>
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs text-gray-500">{{ __('Remaining') }}</p><p class="text-lg font-bold text-green-700">{{ number_format($kpis['remaining'], 0) }}</p></div>
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs text-gray-500">{{ __('Utilization') }}</p><p class="text-lg font-bold text-amber-600">{{ $kpis['utilization'] }}%</p></div>
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs text-gray-500">{{ __('Active Budgets') }}</p><p class="text-lg font-bold text-gray-800">{{ $kpis['active_count'] }}</p></div>
    </div>

    {{-- Alerts --}}
    @if(count($alerts))
    <div class="space-y-2">
        @foreach($alerts as $al)
        <div class="px-4 py-2 rounded-lg text-sm border
            {{ $al['level'] === 'danger' ? 'bg-red-50 border-red-200 text-red-800' : ($al['level'] === 'warning' ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-blue-50 border-blue-200 text-blue-800') }}">
            <strong>{{ $al['type'] }}:</strong> {{ $al['name'] }} — {{ $al['pct'] }}% {{ __('utilized') }}
        </div>
        @endforeach
    </div>
    @endif

    {{-- Charts --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h4 class="text-center text-sm font-semibold text-gray-600 mb-3">{{ __('Department Spending') }}</h4>
            <canvas id="deptChart" height="220"></canvas>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h4 class="text-center text-sm font-semibold text-gray-600 mb-3">{{ __('Category: Allocated vs Spent') }}</h4>
            <canvas id="categoryChart" height="220"></canvas>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h4 class="text-center text-sm font-semibold text-gray-600 mb-3">{{ __('Monthly Budgeted Spend') }} ({{ now()->year }})</h4>
        <canvas id="trendChart" height="90"></canvas>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const palette = ['#34d399','#a5b4fc','#38bdf8','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#64748b','#f97316','#0ea5e9'];

    const dept = @json($deptSpending);
    if (dept.length) new Chart(document.getElementById('deptChart'), {
        type: 'doughnut',
        data: { labels: dept.map(d => d.label), datasets: [{ data: dept.map(d => d.value), backgroundColor: palette }] },
        options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } } }
    });

    const cat = @json($byCategory);
    if (cat.length) new Chart(document.getElementById('categoryChart'), {
        type: 'bar',
        data: {
            labels: cat.map(c => c.label),
            datasets: [
                { label: '{{ __('Allocated') }}', data: cat.map(c => c.allocated), backgroundColor: '#a5b4fc' },
                { label: '{{ __('Spent') }}', data: cat.map(c => c.spent), backgroundColor: '#ef4444' },
            ]
        },
        options: { scales: { y: { beginAtZero: true } } }
    });

    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: { labels: @json($months), datasets: [{ label: '{{ __('Spend') }}', data: @json($monthlySpend), borderColor: '#06b6d4', backgroundColor: 'rgba(6,182,212,0.1)', fill: true, tension: 0.3 }] },
        options: { scales: { y: { beginAtZero: true } } }
    });
</script>
@endpush
@endsection
