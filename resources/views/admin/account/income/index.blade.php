@extends('layouts.admin')

@section('title', __('Income List'))
@section('breadcrumb', __('Income & Expense > Income'))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'CFA')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-700">{{ __('Income List') }}</h3>
        <a href="{{ route('admin.account.income.create') }}" class="bg-[#1e293b] hover:bg-[#334155] text-white px-4 py-2 rounded-lg text-sm font-medium">+ {{ __('Add New') }}</a>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Title') }}</label>
            <input type="text" name="title" value="{{ request('title') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Category') }}</label>
            <select name="category_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">{{ __('All') }}</option>
                @foreach($categories as $c)
                    <option value="{{ $c->id }}" {{ (string) request('category_id') === (string) $c->id ? 'selected' : '' }}>{{ $c->title }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('From Date') }}</label>
            <input type="date" name="from_date" value="{{ $from }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('To Date') }}</label>
            <input type="date" name="to_date" value="{{ $to }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Filter') }}</button>
        </div>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Title') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Category') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Invoice ID') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Amount') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Date') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Method') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Account') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($incomes as $i => $income)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $incomes->firstItem() + $i }}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $income->title }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $income->category?->title }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $income->invoice_id ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-right font-medium text-green-700">{{ number_format($income->amount, 2) }} {{ $currency }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $income->date->format('d/m/Y') }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $income->payment_method ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm">
                        @if($income->paymentAccount)
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-blue-100 text-blue-700">{{ $income->paymentAccount->title }}</span>
                        @else
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500">{{ __('Unlinked') }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                        <a href="{{ route('admin.account.income.edit', $income) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">{{ __('Edit') }}</a>
                        <form method="POST" action="{{ route('admin.account.income.destroy', $income) }}" class="inline" onsubmit="return confirm('{{ __('Delete this income?') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium">{{ __('Delete') }}</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="px-4 py-8 text-center text-gray-500">{{ __('No income records found.') }}</td></tr>
                @endforelse
            </tbody>
            @if($incomes->count())
            <tfoot class="bg-gray-50 border-t border-gray-200">
                <tr>
                    <td colspan="4" class="px-4 py-3 text-sm font-semibold text-gray-700 text-right">{{ __('Total (filtered)') }}</td>
                    <td class="px-4 py-3 text-sm font-bold text-green-700 text-right">{{ number_format($total, 2) }} {{ $currency }}</td>
                    <td colspan="4"></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

    <div>{{ $incomes->links() }}</div>
</div>
@endsection
