@extends('layouts.admin')

@section('title', __('Budget Variance'))
@section('breadcrumb', __('Budgets > Reports > Variance'))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'CFA')

@section('content')
<div class="space-y-4">
    <h3 class="text-lg font-semibold text-gray-700">{{ __('Budget Reports') }}</h3>
    @include('admin.budget.reports._nav')

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Budget') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Planned (Total)') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Actual (Spent)') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Variance') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Variance %') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($budgets as $row)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $row->budget->title }} <span class="text-xs text-gray-400">{{ $row->budget->budget_code }}</span></td>
                    <td class="px-4 py-3 text-sm text-right">{{ number_format($row->budget->total_amount, 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right text-red-700">{{ number_format($row->budget->spent_amount, 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right font-medium {{ $row->variance < 0 ? 'text-red-700' : 'text-green-700' }}">{{ number_format($row->variance, 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right {{ $row->variance < 0 ? 'text-red-700' : 'text-green-700' }}">{{ $row->variance_pct }}%</td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">{{ __('No budgets.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="text-xs text-gray-400">{{ __('Positive variance = under budget (money left). Negative = over budget.') }}</p>
</div>
@endsection
