@extends('layouts.admin')

@section('title', __('Edit Asset'))
@section('breadcrumb', __('Accounting > Fixed Assets > Edit'))

@section('content')
<div class="space-y-6 max-w-5xl">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Edit Asset') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $asset->code }} · {{ $asset->name }}</p>
        </div>
        <a href="{{ route('admin.fixed-assets.schedule', $asset) }}" class="text-sm text-blue-600 hover:underline">{{ __('Back to schedule') }}</a>
    </div>

    @if(session('error'))
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700 font-medium">{{ session('error') }}</div>
    @endif
    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <ul class="text-sm text-red-700 list-disc list-inside">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    </div>
    @endif

    <div class="{{ $posted ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-blue-50 border-blue-200 text-blue-800' }} border rounded-xl p-4 text-sm">
        {{ $posted
            ? __('Depreciation has been posted for this asset, so its category, acquisition date, cost, salvage value, life and method are fixed. The name, code, description and location can still change.')
            : __('Nothing has been posted yet. Changing the cost, life, method or category rebuilds the depreciation schedule.') }}
    </div>

    @php $locked = $posted ? 'readonly' : ''; @endphp

    <form method="POST" action="{{ route('admin.fixed-assets.update', $asset) }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
        @csrf @method('PUT')

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Category') }}</label>
                <select name="fixed_asset_category_id" required class="w-full rounded-lg border-gray-300 text-sm {{ $posted ? 'bg-gray-100 pointer-events-none' : '' }}">
                    @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('fixed_asset_category_id', $asset->fixed_asset_category_id) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Asset Code') }}</label>
                <input type="text" name="code" value="{{ old('code', $asset->code) }}" required maxlength="40" class="w-full rounded-lg border-gray-300 text-sm">
            </div>
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Name') }}</label>
                <input type="text" name="name" value="{{ old('name', $asset->name) }}" required maxlength="150" class="w-full rounded-lg border-gray-300 text-sm">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Acquired') }}</label>
                <input type="date" name="acquisition_date" value="{{ old('acquisition_date', $asset->acquisition_date?->toDateString()) }}" required {{ $locked }} class="w-full rounded-lg border-gray-300 text-sm {{ $posted ? 'bg-gray-100' : '' }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Cost') }}</label>
                <input type="number" step="0.01" name="cost" value="{{ old('cost', (float) $asset->cost) }}" required min="0.01" {{ $locked }} class="w-full rounded-lg border-gray-300 text-sm {{ $posted ? 'bg-gray-100' : '' }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Salvage Value') }}</label>
                <input type="number" step="0.01" name="salvage_value" value="{{ old('salvage_value', (float) $asset->salvage_value) }}" min="0" {{ $locked }} class="w-full rounded-lg border-gray-300 text-sm {{ $posted ? 'bg-gray-100' : '' }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Useful Life (years)') }}</label>
                <input type="number" name="useful_life_years" value="{{ old('useful_life_years', $asset->useful_life_years) }}" required min="1" max="100" {{ $locked }} class="w-full rounded-lg border-gray-300 text-sm {{ $posted ? 'bg-gray-100' : '' }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Method') }}</label>
                <select name="method" class="w-full rounded-lg border-gray-300 text-sm {{ $posted ? 'bg-gray-100 pointer-events-none' : '' }}">
                    <option value="straight_line" @selected(old('method', $asset->method) === 'straight_line')>{{ __('Straight line') }}</option>
                    <option value="declining" @selected(old('method', $asset->method) === 'declining')>{{ __('Declining balance') }}</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Declining Rate (%)') }}</label>
                <input type="number" step="0.01" name="declining_rate" value="{{ old('declining_rate', $asset->declining_rate !== null ? (float) $asset->declining_rate : '') }}" min="0.01" max="100" {{ $locked }} class="w-full rounded-lg border-gray-300 text-sm {{ $posted ? 'bg-gray-100' : '' }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Location') }}</label>
                <input type="text" name="location" value="{{ old('location', $asset->location) }}" maxlength="150" class="w-full rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Description') }}</label>
                <input type="text" name="description" value="{{ old('description', $asset->description) }}" maxlength="255" class="w-full rounded-lg border-gray-300 text-sm">
            </div>
        </div>

        <button type="submit" class="px-4 py-2.5 bg-slate-800 text-white rounded-lg text-sm font-medium hover:bg-slate-700">{{ __('Save Changes') }}</button>
    </form>
</div>
@endsection
