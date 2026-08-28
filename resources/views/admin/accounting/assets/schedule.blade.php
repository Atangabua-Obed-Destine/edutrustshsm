@extends('layouts.admin')

@section('title', __('Depreciation Schedule'))
@section('breadcrumb', __('Accounting > Fixed Assets > Schedule'))

@section('content')
@php
    $currency = \App\Models\SchoolSetting::current()?->currency ?? 'FCFA';
@endphp

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $asset->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ $asset->code }} · {{ $asset->category?->name }} ·
                {{ $asset->method === 'declining' ? __('Declining balance') : __('Straight line') }}
            </p>
        </div>
        <a href="{{ route('admin.fixed-assets.index') }}" class="text-sm text-blue-600 hover:underline">
            {{ __('Back to Fixed Assets') }}
        </a>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <ul class="text-sm text-red-800 space-y-1">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Cost') }}</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format((float) $asset->cost, 0) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Salvage Value') }}</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format((float) $asset->salvage_value, 0) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Depreciated') }}</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format($asset->accumulatedDepreciation(), 0) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ __('posted only') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Book Value') }}</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format($asset->bookValue(), 0) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $currency }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-3 text-left">{{ __('Period') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Date') }}</th>
                    <th class="px-6 py-3 text-right">{{ __('Charge') }}</th>
                    <th class="px-6 py-3 text-right">{{ __('Accumulated') }}</th>
                    <th class="px-6 py-3 text-right">{{ __('Book Value') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($asset->schedules as $row)
                <tr class="hover:bg-gray-50 {{ $row->is_posted ? '' : 'bg-amber-50/40' }}">
                    <td class="px-6 py-3 text-gray-600">{{ $row->period_number }}</td>
                    <td class="px-6 py-3 text-gray-600">{{ $row->period_date?->format('m/Y') }}</td>
                    <td class="px-6 py-3 text-right text-gray-900">{{ number_format((float) $row->amount, 2) }}</td>
                    <td class="px-6 py-3 text-right text-gray-600">{{ number_format((float) $row->accumulated, 2) }}</td>
                    <td class="px-6 py-3 text-right text-gray-600">{{ number_format((float) $row->book_value, 2) }}</td>
                    <td class="px-6 py-3 text-center">
                        @if($row->is_posted)
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">
                            {{ __('Posted') }}
                        </span>
                        @if($row->journalEntry)
                        <span class="text-gray-400 text-xs block">{{ $row->journalEntry->entry_number }}</span>
                        @endif
                        @else
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">
                            {{ __('Pending') }}
                        </span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-right">
                        @if(! $row->is_posted && ! $asset->isDisposed())
                        @can('fixed-asset.depreciate')
                        <form method="POST" action="{{ route('admin.depreciation.post', $row) }}" class="inline">
                            @csrf
                            <button type="submit" class="text-blue-600 hover:underline">{{ __('Post') }}</button>
                        </form>
                        @endcan
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">{{ __('No schedule generated yet.') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(! $asset->isDisposed())
    @can('fixed-asset.dispose')
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-2">{{ __('Dispose of this asset') }}</h3>
        <p class="text-sm text-gray-500 mb-4">
            {{ __('Proceeds above book value are a gain, below it a loss. Unposted periods are voided.') }}
        </p>
        <form method="POST" action="{{ route('admin.fixed-assets.dispose', $asset) }}"
              onsubmit="return confirm('{{ __('Dispose of this asset and post the entry?') }}')">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Disposal Date') }}</label>
                    <input type="date" name="disposal_date" required class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Proceeds') }}</label>
                    <input type="number" step="0.01" name="disposal_amount" min="0" required
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Note') }}</label>
                    <input type="text" name="disposal_note" maxlength="255" class="w-full rounded-lg border-gray-300 text-sm">
                </div>
            </div>
            <button type="submit" class="mt-4 px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-500">
                {{ __('Dispose') }}
            </button>
        </form>
    </div>
    @endcan
    @else
    <div class="bg-slate-50 border border-slate-200 rounded-xl p-6 text-sm text-slate-700">
        {{ __('Disposed on :date for :amount.', [
            'date' => $asset->disposal_date?->format('d/m/Y'),
            'amount' => number_format((float) $asset->disposal_amount, 2).' '.$currency,
        ]) }}
        @if($asset->disposal_note)<span class="block mt-1 text-slate-500">{{ $asset->disposal_note }}</span>@endif
    </div>
    @endif
</div>
@endsection
