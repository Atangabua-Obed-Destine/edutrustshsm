@extends('layouts.admin')

@section('title', __('Budget Performance'))
@section('breadcrumb', __('Budgets > Reports > Performance'))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'CFA')

@section('content')
<div class="space-y-4">
    <h3 class="text-lg font-semibold text-gray-700">{{ __('Budget Reports') }}</h3>
    @include('admin.budget.reports._nav')

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Fiscal Year') }}</label>
            <select name="fiscal_year" style="appearance:auto;-webkit-appearance:menulist;" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">{{ __('All') }}</option>
                @foreach($fiscalYears as $fy)
                    <option value="{{ $fy }}" {{ $fiscalYear === $fy ? 'selected' : '' }}>{{ $fy }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Filter') }}</button>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Budget') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Total') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Allocated') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Spent') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Remaining') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Utilization') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($budgets as $b)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900"><a href="{{ route('admin.budget.show', $b) }}" class="text-blue-600 hover:underline">{{ $b->title }}</a><div class="text-xs text-gray-400">{{ $b->budget_code }}</div></td>
                    <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-full text-xs {{ $b->status_badge }}">{{ ucfirst(str_replace('_',' ',$b->status)) }}</span></td>
                    <td class="px-4 py-3 text-sm text-right">{{ number_format($b->total_amount, 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right text-blue-700">{{ number_format($b->allocated_amount, 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right text-red-700">{{ number_format($b->spent_amount, 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right text-green-700">{{ number_format($b->remaining_amount, 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right font-medium">{{ $b->utilization_percentage }}%</td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">{{ __('No budgets.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="bg-gray-50 border-t border-gray-200">
                <tr>
                    <td colspan="2" class="px-4 py-3 text-sm font-semibold text-right">{{ __('Total') }}</td>
                    <td class="px-4 py-3 text-sm text-right font-bold">{{ number_format($budgets->sum('total_amount'), 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right font-bold text-blue-700">{{ number_format($budgets->sum('allocated_amount'), 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right font-bold text-red-700">{{ number_format($budgets->sum('spent_amount'), 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right font-bold text-green-700">{{ number_format($budgets->sum('remaining_amount'), 2) }} {{ $currency }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
