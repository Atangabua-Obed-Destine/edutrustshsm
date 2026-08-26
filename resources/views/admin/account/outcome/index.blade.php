@extends('layouts.admin')

@section('title', __('Outcome Overview'))
@section('breadcrumb', __('Income & Expense > Outcome Overview'))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'CFA')

@section('content')
<div class="space-y-4">
    <h3 class="text-lg font-semibold text-gray-700">{{ __('Outcome Overview') }}</h3>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Start Date') }}</label>
            <input type="date" name="from_date" value="{{ $from }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('End Date') }}</label>
            <input type="date" name="to_date" value="{{ $to }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div>
            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Search') }}</button>
        </div>
    </form>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="text-white rounded-xl p-5" style="background:linear-gradient(to right,#2dd4bf,#06b6d4);">
            <p class="text-sm opacity-90">{{ __('Overall Income') }}</p>
            <p class="text-3xl font-bold">{{ $currency }} {{ number_format($totalIncome, 2) }}</p>
        </div>
        <div class="text-white rounded-xl p-5" style="background:linear-gradient(to right,#a78bfa,#6366f1);">
            <p class="text-sm opacity-90">{{ __('Overall Expense') }}</p>
            <p class="text-3xl font-bold">{{ $currency }} {{ number_format($totalExpense, 2) }}</p>
        </div>
        <div class="text-white rounded-xl p-5" style="background:linear-gradient(to right,#38bdf8,#3b82f6);">
            <p class="text-sm opacity-90">{{ __('Total Outcome') }}</p>
            <p class="text-3xl font-bold">{{ $currency }} {{ number_format($net, 2) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h4 class="text-center text-sm font-semibold text-gray-600 mb-3">{{ __('Incomes') }}</h4>
            <canvas id="incomeChart" height="220"></canvas>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h4 class="text-center text-sm font-semibold text-gray-600 mb-3">{{ __('Expenses') }}</h4>
            <canvas id="expenseChart" height="220"></canvas>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h4 class="text-center text-sm font-semibold text-gray-600 mb-3">{{ __('Monthly Trend') }} ({{ now()->year }})</h4>
        <canvas id="trendChart" height="90"></canvas>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const palette = ['#34d399','#a5b4fc','#38bdf8','#1e293b','#f59e0b','#ef4444','#10b981','#8b5cf6','#06b6d4','#64748b','#f97316','#0ea5e9'];

    function donut(id, data) {
        const ctx = document.getElementById(id);
        if (!ctx) return;
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.map(d => d.label),
                datasets: [{ data: data.map(d => d.value), backgroundColor: palette }],
            },
            options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } } }
        });
    }

    donut('incomeChart', @json($incomeByCategory));
    donut('expenseChart', @json($expenseByCategory));

    new Chart(document.getElementById('trendChart'), {
        type: 'bar',
        data: {
            labels: @json($months),
            datasets: [
                { label: '{{ __('Overall Income') }}', data: @json($monthlyIncome), backgroundColor: '#06b6d4' },
                { label: '{{ __('Overall Expense') }}', data: @json($monthlyExpense), backgroundColor: '#a5b4fc' },
            ]
        },
        options: { scales: { y: { beginAtZero: true } } }
    });
</script>
@endpush
@endsection
