@extends('layouts.admin')

@section('title', __('Budget by Department'))
@section('breadcrumb', __('Budgets > Reports > By Department'))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'CFA')

@section('content')
<div class="space-y-4">
    <h3 class="text-lg font-semibold text-gray-700">{{ __('Budget Reports') }}</h3>
    @include('admin.budget.reports._nav')

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Department') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Budgets') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Total') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Spent') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Remaining') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($rows as $r)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $r->department }}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-600">{{ $r->count }}</td>
                    <td class="px-4 py-3 text-sm text-right">{{ number_format($r->total, 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right text-red-700">{{ number_format($r->spent, 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right text-green-700">{{ number_format($r->remaining, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">{{ __('No departmental budgets.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="bg-gray-50 border-t border-gray-200">
                <tr>
                    <td colspan="2" class="px-4 py-3 text-sm font-semibold text-right">{{ __('Total') }}</td>
                    <td class="px-4 py-3 text-sm text-right font-bold">{{ number_format($rows->sum('total'), 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right font-bold text-red-700">{{ number_format($rows->sum('spent'), 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right font-bold text-green-700">{{ number_format($rows->sum('remaining'), 2) }} {{ $currency }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
