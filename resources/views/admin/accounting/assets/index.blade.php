@extends('layouts.admin')

@section('title', __('Fixed Assets'))
@section('breadcrumb', __('Accounting > Fixed Assets'))

@section('content')
@php
    $currency = \App\Models\SchoolSetting::current()?->currency ?? 'FCFA';
@endphp

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Fixed Assets') }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ __('Book value is net of depreciation actually posted to the ledger, not of the whole schedule.') }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            @can('fixed-asset.view')
            <a href="{{ route('admin.fixed-assets.categories') }}" class="text-sm text-blue-600 hover:underline">
                {{ __('Categories') }}
            </a>
            @endcan
            @if($dueCount > 0)
            @can('fixed-asset.depreciate')
            <form method="POST" action="{{ route('admin.fixed-assets.post-due') }}">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2.5 bg-slate-800 text-white rounded-lg hover:bg-slate-700 font-medium text-sm">
                    {{ trans_choice('Post :count due period|Post :count due periods', $dueCount, ['count' => $dueCount]) }}
                </button>
            </form>
            @endcan
            @endif
        </div>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <ul class="text-sm text-red-800 space-y-1">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Active Assets') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $assets->total() }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Total Cost') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($totalCost, 0) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $currency }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Periods Due') }}</p>
            <p class="text-2xl font-bold {{ $dueCount > 0 ? 'text-amber-600' : 'text-gray-900' }} mt-1">{{ $dueCount }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-3 text-left">{{ __('Asset') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Category') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Acquired') }}</th>
                    <th class="px-6 py-3 text-right">{{ __('Cost') }}</th>
                    <th class="px-6 py-3 text-right">{{ __('Depreciated') }}</th>
                    <th class="px-6 py-3 text-right">{{ __('Book Value') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($assets as $asset)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3">
                        <span class="font-medium text-gray-900">{{ $asset->name }}</span>
                        <span class="text-gray-400 text-xs block">{{ $asset->code }}</span>
                    </td>
                    <td class="px-6 py-3 text-gray-600">{{ $asset->category?->name }}</td>
                    <td class="px-6 py-3 text-gray-600">{{ $asset->acquisition_date?->format('d/m/Y') }}</td>
                    <td class="px-6 py-3 text-right text-gray-900">{{ number_format((float) $asset->cost, 0) }}</td>
                    <td class="px-6 py-3 text-right text-gray-600">{{ number_format($asset->accumulated, 0) }}</td>
                    <td class="px-6 py-3 text-right font-semibold text-gray-900">{{ number_format($asset->net_book_value, 0) }}</td>
                    <td class="px-6 py-3 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold
                            {{ $asset->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ __(ucfirst(str_replace('_', ' ', $asset->status))) }}
                        </span>
                    </td>
                    <td class="px-6 py-3 text-right">
                        <a href="{{ route('admin.fixed-assets.schedule', $asset) }}" class="text-blue-600 hover:underline">
                            {{ __('Schedule') }}
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-gray-500">{{ __('No assets registered yet.') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $assets->links() }}

    @can('fixed-asset.create')
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ __('Register an asset') }}</h3>
        @if($categories->isEmpty())
        <p class="text-sm text-gray-500">
            {{ __('Create an asset category first — it decides which accounts depreciation is booked to.') }}
        </p>
        @else
        <form method="POST" action="{{ route('admin.fixed-assets.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Category') }}</label>
                    <select name="fixed_asset_category_id" required class="w-full rounded-lg border-gray-300 text-sm">
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Asset Code') }}</label>
                    <input type="text" name="code" value="{{ old('code') }}" required maxlength="40"
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div class="lg:col-span-2">
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Name') }}</label>
                    <input type="text" name="name" value="{{ old('name') }}" required maxlength="150"
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Acquired') }}</label>
                    <input type="date" name="acquisition_date" value="{{ old('acquisition_date') }}" required
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Cost') }}</label>
                    <input type="number" step="0.01" name="cost" value="{{ old('cost') }}" required min="0.01"
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Salvage Value') }}</label>
                    <input type="number" step="0.01" name="salvage_value" value="{{ old('salvage_value', 0) }}" min="0"
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Useful Life (years)') }}</label>
                    <input type="number" name="useful_life_years" value="{{ old('useful_life_years', 5) }}" required min="1" max="100"
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Method') }}</label>
                    <select name="method" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="straight_line">{{ __('Straight line') }}</option>
                        <option value="declining">{{ __('Declining balance') }}</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">
                        {{ __('Declining Rate (%)') }} <span class="text-gray-400">({{ __('declining only') }})</span>
                    </label>
                    <input type="number" step="0.01" name="declining_rate" value="{{ old('declining_rate') }}" min="0.01" max="100"
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Location') }}</label>
                    <input type="text" name="location" value="{{ old('location') }}" maxlength="150"
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
            </div>

            <button type="submit" class="px-4 py-2.5 bg-slate-800 text-white rounded-lg text-sm font-medium hover:bg-slate-700">
                {{ __('Register Asset') }}
            </button>
        </form>
        @endif
    </div>
    @endcan
</div>
@endsection
