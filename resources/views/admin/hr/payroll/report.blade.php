@extends('layouts.admin')

@section('title', __('Payroll Report'))
@section('breadcrumb', __('Human Resources > Payroll Reports'))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'CFA')
@php($months = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'])

@section('content')
<div class="space-y-4">
    <h3 class="text-lg font-semibold text-gray-700">{{ __('Payroll Report') }}</h3>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs text-gray-500 mb-1">{{ __('Month') }}</label>
            <select name="month" style="appearance:auto;-webkit-appearance:menulist;" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                @foreach($months as $n => $name)<option value="{{ $n }}" {{ $month==$n?'selected':'' }}>{{ __($name) }}</option>@endforeach
            </select>
        </div>
        <div><label class="block text-xs text-gray-500 mb-1">{{ __('Year') }}</label><input type="number" name="year" value="{{ $year }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm w-28"></div>
        <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Filter') }}</button>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <p class="px-4 py-3 text-center font-semibold text-gray-600">{{ strtoupper(__($months[$month])) }} {{ $year }}</p>
        <table class="w-full">
            <thead class="bg-blue-500 text-white">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase">{{ __('Staff') }}</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">{{ __('Basic') }}</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">{{ __('Allowance') }}</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">{{ __('Deduction') }}</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">{{ __('Gross') }}</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">{{ __('Tax') }}</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">{{ __('Net') }}</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($payrolls as $p)
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2 text-sm font-medium text-gray-900">{{ $p->user?->full_name }}</td>
                    <td class="px-3 py-2 text-sm text-right">{{ number_format($p->basic_salary, 0) }}</td>
                    <td class="px-3 py-2 text-sm text-right text-emerald-700">{{ number_format($p->total_allowance + $p->bonus, 0) }}</td>
                    <td class="px-3 py-2 text-sm text-right text-red-700">{{ number_format($p->total_deduction, 0) }}</td>
                    <td class="px-3 py-2 text-sm text-right">{{ number_format($p->gross_salary, 0) }}</td>
                    <td class="px-3 py-2 text-sm text-right text-red-700">{{ number_format($p->tax, 0) }}</td>
                    <td class="px-3 py-2 text-sm text-right font-medium text-green-700">{{ number_format($p->net_salary, 0) }}</td>
                    <td class="px-3 py-2"><span class="inline-flex px-2 py-0.5 rounded-full text-xs {{ $p->status ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ $p->status ? __('Paid') : __('Unpaid') }}</span></td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-3 py-8 text-center text-gray-500">{{ __('No payroll generated for this period.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="bg-gray-50 border-t border-gray-200 font-bold">
                <tr>
                    <td class="px-3 py-2 text-sm text-right">{{ __('Grand Total') }}</td>
                    <td class="px-3 py-2 text-sm text-right">{{ number_format($payrolls->sum('basic_salary'), 0) }}</td>
                    <td class="px-3 py-2 text-sm text-right text-emerald-700">{{ number_format($payrolls->sum(fn($p)=>$p->total_allowance+$p->bonus), 0) }}</td>
                    <td class="px-3 py-2 text-sm text-right text-red-700">{{ number_format($payrolls->sum('total_deduction'), 0) }}</td>
                    <td class="px-3 py-2 text-sm text-right">{{ number_format($payrolls->sum('gross_salary'), 0) }}</td>
                    <td class="px-3 py-2 text-sm text-right text-red-700">{{ number_format($payrolls->sum('tax'), 0) }}</td>
                    <td class="px-3 py-2 text-sm text-right text-green-700">{{ number_format($payrolls->sum('net_salary'), 0) }} {{ $currency }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
