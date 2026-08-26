@extends('layouts.admin')

@section('title', __('Tax Distribution Report'))
@section('breadcrumb', __('Human Resources > Settings > Tax Distribution Report'))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'CFA')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-700">{{ __('Tax Distribution Report') }}</h3>
        <form method="GET" class="flex items-end gap-2">
            <div><label class="block text-xs text-gray-500 mb-1">{{ __('As of') }}</label><input type="date" name="date" value="{{ $date }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
            <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Recompute') }}</button>
        </form>
    </div>
    <p class="text-xs text-gray-400">{{ __('Recomputed under current tax rules — matches what the payslip would pay today.') }}</p>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs text-gray-500">{{ __('Total Gross') }}</p><p class="text-lg font-bold text-gray-800">{{ number_format($totals['gross'], 0) }}</p></div>
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs text-gray-500">{{ __('Employee Tax') }}</p><p class="text-lg font-bold text-red-700">{{ number_format($totals['employee_tax'], 0) }}</p></div>
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs text-gray-500">{{ __('Employer Tax') }}</p><p class="text-lg font-bold text-amber-600">{{ number_format($totals['employer_tax'], 0) }}</p></div>
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs text-gray-500">{{ __('Total Net') }}</p><p class="text-lg font-bold text-green-700">{{ number_format($totals['net'], 0) }}</p></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h4 class="font-semibold text-gray-700 mb-3">{{ __('By Salary Band') }}</h4>
            @foreach($distribution as $b)
            <div class="flex justify-between text-sm py-1 border-b border-gray-50"><span class="text-gray-600">{{ $b->label }} <span class="text-xs text-gray-400">({{ $b->count }})</span></span><span class="text-red-700">{{ number_format($b->employee_tax, 0) }}</span></div>
            @endforeach
        </div>

        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Staff') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Gross') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Emp. Tax') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Empr. Tax') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Net') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Eff. %') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($rows as $r)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 text-sm font-medium text-gray-900">{{ $r->staff->full_name }} <span class="text-xs text-gray-400">{{ $r->staff->staff_id }}</span></td>
                        <td class="px-3 py-2 text-sm text-right">{{ number_format($r->gross, 0) }}</td>
                        <td class="px-3 py-2 text-sm text-right text-red-700">{{ number_format($r->employee_tax, 0) }}</td>
                        <td class="px-3 py-2 text-sm text-right text-amber-600">{{ number_format($r->employer_tax, 0) }}</td>
                        <td class="px-3 py-2 text-sm text-right text-green-700">{{ number_format($r->net, 0) }}</td>
                        <td class="px-3 py-2 text-sm text-right">{{ $r->effective }}%</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-3 py-8 text-center text-gray-500">{{ __('No staff with salaries configured.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
