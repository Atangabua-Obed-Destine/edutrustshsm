@extends('layouts.admin')

@section('title', __('Chart of Accounts'))
@section('breadcrumb', __('Accounting > Chart of Accounts'))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'FCFA')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-700">{{ __('Chart of Accounts') }} <span class="text-sm text-gray-400">(Plan Comptable)</span></h3>
        <a href="{{ route('admin.chart-of-accounts.create') }}" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">+ {{ __('Add Account') }}</a>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="text-white rounded-xl p-5" style="background:linear-gradient(to right,#818cf8,#6366f1);"><p class="text-2xl font-bold">{{ $stats['total'] }}</p><p class="text-sm opacity-90">{{ __('Total Accounts') }}</p></div>
        <div class="text-white rounded-xl p-5" style="background:linear-gradient(to right,#34d399,#10b981);"><p class="text-2xl font-bold">{{ $stats['active'] }}</p><p class="text-sm opacity-90">{{ __('Active Accounts') }}</p></div>
        <div class="text-white rounded-xl p-5" style="background:linear-gradient(to right,#f472b6,#ec4899);"><p class="text-2xl font-bold">{{ $stats['detail'] }}</p><p class="text-sm opacity-90">{{ __('Detail Accounts') }}</p></div>
        <div class="text-white rounded-xl p-5" style="background:linear-gradient(to right,#fb923c,#f59e0b);"><p class="text-2xl font-bold">{{ number_format($stats['balance'], 0) }} {{ $currency }}</p><p class="text-sm opacity-90">{{ __('Total Balance') }}</p></div>
    </div>

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.chart-of-accounts.index') }}" class="px-3 py-1.5 rounded-lg text-sm {{ !$class ? 'bg-blue-500 text-white' : 'bg-white border border-gray-200 text-gray-600' }}">{{ __('All Classes') }}</a>
        @for($i = 1; $i <= 8; $i++)
            <a href="{{ route('admin.chart-of-accounts.index', ['class' => $i]) }}" class="px-3 py-1.5 rounded-lg text-sm {{ (string)$class === (string)$i ? 'bg-blue-500 text-white' : 'bg-white border border-gray-200 text-gray-600' }}">{{ __('classe') }} {{ $i }}</a>
        @endfor
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Code') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Account Name') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Class') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Type') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Normal') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Balance') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($accounts as $a)
                <tr class="hover:bg-gray-50 {{ $a->account_category !== 'detail' ? 'bg-gray-50/50 font-semibold' : '' }}">
                    <td class="px-4 py-2.5 text-sm font-mono text-gray-700">{{ $a->account_code }}</td>
                    <td class="px-4 py-2.5 text-sm text-gray-900">{{ $a->account_name }} <span class="text-xs text-gray-400">{{ $a->account_name_fr }}</span></td>
                    <td class="px-4 py-2.5 text-sm text-gray-600">{{ $a->class_number }}</td>
                    <td class="px-4 py-2.5 text-sm text-gray-600 capitalize">{{ $a->account_type }}</td>
                    <td class="px-4 py-2.5 text-sm text-gray-600 capitalize">{{ $a->normal_balance }}</td>
                    <td class="px-4 py-2.5 text-sm text-right text-gray-700">{{ number_format($a->current_balance, 2) }}</td>
                    <td class="px-4 py-2.5">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs {{ $a->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $a->is_active ? __('Active') : __('Inactive') }}</span>
                        @if($a->is_system)<span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-blue-50 text-blue-600">{{ __('System') }}</span>@endif
                    </td>
                    <td class="px-4 py-2.5 text-right space-x-2 whitespace-nowrap">
                        <a href="{{ route('admin.chart-of-accounts.edit', $a) }}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">{{ __('Edit') }}</a>
                        @unless($a->is_system)
                        <form method="POST" action="{{ route('admin.chart-of-accounts.toggle-status', $a) }}" class="inline">@csrf <button class="text-amber-600 hover:text-amber-800 text-xs font-medium">{{ $a->is_active ? __('Disable') : __('Enable') }}</button></form>
                        @endunless
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">{{ __('No data found') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
