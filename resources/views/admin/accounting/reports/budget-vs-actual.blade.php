@extends('layouts.admin')

@section('title', __('Budget vs Actual'))
@section('breadcrumb', __('Accounting > Reports > Budget vs Actual'))

@section('content')
@php
    $currency = \App\Models\SchoolSetting::current()?->currency ?? 'FCFA';
@endphp

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Budget vs Actual') }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ __('Positive variance = under budget (money left). Negative = over budget.') }}
            </p>
        </div>
        <a href="{{ route('admin.accounting-reports.index') }}" class="text-sm text-blue-600 hover:underline">
            {{ __('Back to Reports') }}
        </a>
    </div>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Status') }}</label>
                <select name="status" class="rounded-lg border-gray-300 text-sm">
                    <option value="">{{ __('All Budgets') }}</option>
                    @foreach(['draft', 'pending_approval', 'approved', 'active', 'closed', 'cancelled'] as $s)
                    <option value="{{ $s }}" @selected($status === $s)>{{ __(ucfirst(str_replace('_', ' ', $s))) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-lg text-sm font-medium hover:bg-slate-700">
                {{ __('Filter') }}
            </button>
        </div>
    </form>

    @forelse($budgets as $row)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="font-semibold text-gray-900">
                    {{ $row->budget->name }}
                    <span class="text-gray-400 text-sm">{{ $row->budget->budget_code }}</span>
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">{{ __(ucfirst(str_replace('_', ' ', $row->budget->status))) }}</p>
            </div>
            <div class="flex items-center gap-6 text-sm">
                <div class="text-right">
                    <p class="text-xs text-gray-500">{{ __('Planned (Total)') }}</p>
                    <p class="font-semibold text-gray-900">{{ number_format($row->planned, 0) }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-500">{{ __('Actual (Spent)') }}</p>
                    <p class="font-semibold text-gray-900">{{ number_format($row->spent, 0) }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-500">{{ __('Variance') }}</p>
                    <p class="font-semibold {{ $row->variance < 0 ? 'text-red-600' : 'text-emerald-600' }}">
                        {{ number_format($row->variance, 0) }}
                    </p>
                </div>
                <div class="text-right w-24">
                    <p class="text-xs text-gray-500">{{ __('Utilization') }}</p>
                    <div class="mt-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full {{ $row->utilisation > 100 ? 'bg-red-500' : 'bg-emerald-500' }}"
                             style="width: {{ min($row->utilisation, 100) }}%"></div>
                    </div>
                    <p class="text-xs text-gray-600 mt-1">{{ $row->utilisation }}%</p>
                </div>
            </div>
        </div>

        @if($row->lines->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3 text-left">{{ __('Category') }}</th>
                        <th class="px-6 py-3 text-right">{{ __('Planned (Total)') }}</th>
                        <th class="px-6 py-3 text-right">{{ __('Actual (Spent)') }}</th>
                        <th class="px-6 py-3 text-right">{{ __('Variance') }} ({{ $currency }})</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($row->lines as $line)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 text-gray-900">{{ $line->label }}</td>
                        <td class="px-6 py-3 text-right text-gray-600">{{ number_format($line->planned, 2) }}</td>
                        <td class="px-6 py-3 text-right text-gray-600">{{ number_format($line->spent, 2) }}</td>
                        <td class="px-6 py-3 text-right font-medium {{ $line->variance < 0 ? 'text-red-600' : 'text-gray-900' }}">
                            {{ number_format($line->variance, 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <p class="px-6 py-6 text-sm text-gray-500">{{ __('No allocations yet.') }}</p>
        @endif
    </div>
    @empty
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center text-gray-500">
        {{ __('No budgets found.') }}
    </div>
    @endforelse
</div>
@endsection
