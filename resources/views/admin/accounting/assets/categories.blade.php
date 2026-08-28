@extends('layouts.admin')

@section('title', __('Asset Categories'))
@section('breadcrumb', __('Accounting > Fixed Assets > Categories'))

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Asset Categories') }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ __('A category decides which accounts an asset and its depreciation are booked to.') }}
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

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-3 text-left">{{ __('Name') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Useful Life (years)') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Method') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Asset Account') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Depreciation') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Accumulated') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Assets') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($categories as $category)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 font-medium text-gray-900">{{ $category->name }}</td>
                    <td class="px-6 py-3 text-center text-gray-600">{{ $category->useful_life_years }}</td>
                    <td class="px-6 py-3 text-gray-600">
                        {{ $category->method === 'declining' ? __('Declining balance') : __('Straight line') }}
                        @if($category->method === 'declining' && $category->declining_rate)
                        <span class="text-gray-400">({{ rtrim(rtrim(number_format((float) $category->declining_rate, 2), '0'), '.') }}%)</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-gray-600">{{ $category->assetAccount?->account_code }}</td>
                    <td class="px-6 py-3 text-gray-600">{{ $category->depreciationAccount?->account_code }}</td>
                    <td class="px-6 py-3 text-gray-600">{{ $category->accumulatedAccount?->account_code }}</td>
                    <td class="px-6 py-3 text-center text-gray-600">{{ $category->assets_count }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">{{ __('No asset categories yet.') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @can('fixed-asset.create')
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ __('Add a category') }}</h3>
        <form method="POST" action="{{ route('admin.fixed-assets.categories.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Name') }}</label>
                    <input type="text" name="name" value="{{ old('name') }}" required maxlength="100"
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
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Declining Rate (%)') }}</label>
                    <input type="number" step="0.01" name="declining_rate" value="{{ old('declining_rate') }}" min="0.01" max="100"
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @foreach([
                    'asset_account_id' => __('Asset Account'),
                    'depreciation_account_id' => __('Depreciation Expense Account'),
                    'accumulated_account_id' => __('Accumulated Depreciation Account'),
                ] as $field => $label)
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ $label }}</label>
                    <select name="{{ $field }}" required class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">{{ __('Select an account') }}</option>
                        @foreach($accounts as $account)
                        <option value="{{ $account->id }}" @selected(old($field) == $account->id)>
                            {{ $account->account_code }} — {{ $account->account_name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                @endforeach
            </div>

            <button type="submit" class="px-4 py-2.5 bg-slate-800 text-white rounded-lg text-sm font-medium hover:bg-slate-700">
                {{ __('Add Category') }}
            </button>
        </form>
    </div>
    @endcan
</div>
@endsection
