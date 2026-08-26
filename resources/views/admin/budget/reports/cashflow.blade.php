@extends('layouts.admin')

@section('title', __('Budget Cash Flow'))
@section('breadcrumb', __('Budgets > Reports > Cash Flow'))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'CFA')

@section('content')
<div class="space-y-4">
    <h3 class="text-lg font-semibold text-gray-700">{{ __('Budget Reports') }}</h3>
    @include('admin.budget.reports._nav')

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Year') }}</label>
            <input type="number" name="year" value="{{ $year }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm w-32">
        </div>
        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Filter') }}</button>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Month') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Budgeted Spend') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach($months as $m)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $m->label }}</td>
                    <td class="px-4 py-3 text-sm text-right text-red-700">{{ number_format($m->spent, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 border-t border-gray-200">
                <tr>
                    <td class="px-4 py-3 text-sm font-semibold text-right">{{ __('Total') }}</td>
                    <td class="px-4 py-3 text-sm text-right font-bold text-red-700">{{ number_format(collect($months)->sum('spent'), 2) }} {{ $currency }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
