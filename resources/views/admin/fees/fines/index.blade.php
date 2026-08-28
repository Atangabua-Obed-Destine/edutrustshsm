@extends('layouts.admin')

@section('title', __('Late Fees'))
@section('breadcrumb', __('Fees > Late Fees'))

@section('content')
@php
    $currency = \App\Models\SchoolSetting::current()?->currency ?? 'FCFA';
@endphp

<div class="space-y-6" x-data="{ editing: null }">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Late Fees') }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ __('Penalty bands applied to fees that pass their due date.') }}
            </p>
        </div>
        @can('fee-fine.edit')
        <form method="POST" action="{{ route('admin.fee-fines.accrue') }}">
            @csrf
            <button type="submit"
                    class="inline-flex items-center px-4 py-2.5 bg-slate-800 text-white rounded-lg hover:bg-slate-700 font-medium text-sm transition">
                {{ __('Run Accrual Now') }}
            </button>
        </form>
        @endcan
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-900">
        {{ __('Penalties are recomputed nightly from these bands — never added up. Re-running accrual is safe, and deactivating a band removes its charges on the next run.') }}
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Bands') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $fines->count() }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Fees Charged') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($feesCharged) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Total Accrued') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($accrued, 0) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $currency }}</p>
        </div>
    </div>

    {{-- Existing bands --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-3 text-left">{{ __('Title') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Applies after') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Charge') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Fee Category') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($fines as $fine)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 font-medium text-gray-900">{{ $fine->title }}</td>
                    <td class="px-6 py-3 text-gray-600">
                        @if($fine->end_day)
                            {{ __(':a-:b days late', ['a' => $fine->start_day, 'b' => $fine->end_day]) }}
                        @else
                            {{ __(':n+ days late', ['n' => $fine->start_day]) }}
                        @endif
                    </td>
                    <td class="px-6 py-3 text-gray-900">
                        @if($fine->type === 'percentage')
                            {{ rtrim(rtrim(number_format((float) $fine->amount, 2), '0'), '.') }}%
                        @else
                            {{ number_format((float) $fine->amount, 0) }} {{ $currency }}
                        @endif
                    </td>
                    <td class="px-6 py-3 text-gray-600">
                        @if($fine->feeCategories->isEmpty())
                            <span class="text-gray-400">{{ __('All fee categories') }}</span>
                        @else
                            {{ $fine->feeCategories->pluck('name')->join(', ') }}
                        @endif
                    </td>
                    <td class="px-6 py-3 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold
                            {{ $fine->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ $fine->is_active ? __('Active') : __('Inactive') }}
                        </span>
                    </td>
                    <td class="px-6 py-3 text-right">
                        @can('fee-fine.delete')
                        <form method="POST" action="{{ route('admin.fee-fines.destroy', $fine) }}" class="inline"
                              onsubmit="return confirm('{{ __('Delete this late fee band?') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                        {{ __('No late fee bands defined. Fees never attract a penalty until you add one.') }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- New band --}}
    @can('fee-fine.create')
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ __('Add a band') }}</h3>
        <form method="POST" action="{{ route('admin.fee-fines.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="lg:col-span-2">
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Title') }}</label>
                    <input type="text" name="title" value="{{ old('title') }}" required
                           placeholder="{{ __('e.g. First month late') }}"
                           class="w-full rounded-lg border-gray-300 text-sm">
                    @error('title')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('From day') }}</label>
                    <input type="number" name="start_day" value="{{ old('start_day', 1) }}" min="1" required
                           class="w-full rounded-lg border-gray-300 text-sm">
                    @error('start_day')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">
                        {{ __('To day') }} <span class="text-gray-400">({{ __('blank = no limit') }})</span>
                    </label>
                    <input type="number" name="end_day" value="{{ old('end_day') }}" min="1"
                           class="w-full rounded-lg border-gray-300 text-sm">
                    @error('end_day')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Type') }}</label>
                    <select name="type" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="fixed" @selected(old('type') === 'fixed')>{{ __('Fixed Amount') }}</option>
                        <option value="percentage" @selected(old('type') === 'percentage')>{{ __('Percentage') }}</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Amount') }}</label>
                    <input type="number" step="0.01" name="amount" value="{{ old('amount') }}" min="0" required
                           class="w-full rounded-lg border-gray-300 text-sm">
                    @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">
                        {{ __('Fee Category') }} <span class="text-gray-400">({{ __('none = all') }})</span>
                    </label>
                    <select name="fee_categories[]" multiple size="3" class="w-full rounded-lg border-gray-300 text-sm">
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300">
                {{ __('Active') }}
            </label>

            <div>
                <button type="submit" class="px-4 py-2.5 bg-slate-800 text-white rounded-lg text-sm font-medium hover:bg-slate-700">
                    {{ __('Add Band') }}
                </button>
            </div>
        </form>
    </div>
    @endcan
</div>
@endsection
