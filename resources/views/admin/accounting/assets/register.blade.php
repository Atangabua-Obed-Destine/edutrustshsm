@extends('layouts.admin')

@section('title', __('Asset Register'))
@section('breadcrumb', __('Accounting > Fixed Assets > Register'))

@section('content')
@php $money = fn ($v) => number_format((float) $v, 0, '.', ' '); @endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Asset Register') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('Every asset held, with its cost, the depreciation posted to date and its book value.') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.fixed-assets.register', request()->query() + ['export' => 'csv']) }}" class="border border-gray-300 hover:bg-gray-50 px-3 py-1.5 rounded-lg text-sm">CSV</a>
            <a href="{{ route('admin.fixed-assets.index') }}" class="text-sm text-blue-600 hover:underline">{{ __('Back to Fixed Assets') }}</a>
        </div>
    </div>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('As of') }}</label>
            <input type="date" name="as_of" value="{{ $asOf }}" class="rounded-lg border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Category') }}</label>
            <select name="category_id" class="rounded-lg border-gray-300 text-sm">
                <option value="">{{ __('All') }}</option>
                @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Status') }}</label>
            <select name="status" class="rounded-lg border-gray-300 text-sm">
                <option value="">{{ __('All') }}</option>
                @foreach(['active', 'disposed', 'written_off'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ __(ucfirst(str_replace('_', ' ', $status))) }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-lg text-sm font-medium hover:bg-slate-700">{{ __('Filter') }}</button>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-5 py-3 text-left">{{ __('Asset') }}</th>
                    <th class="px-5 py-3 text-left">{{ __('Category') }}</th>
                    <th class="px-5 py-3 text-left">{{ __('Acquired') }}</th>
                    <th class="px-5 py-3 text-center">{{ __('Status') }}</th>
                    <th class="px-5 py-3 text-right">{{ __('Cost') }}</th>
                    <th class="px-5 py-3 text-right">{{ __('Depreciated') }}</th>
                    <th class="px-5 py-3 text-right">{{ __('Book Value') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rows as $row)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3">
                        <a href="{{ route('admin.fixed-assets.schedule', $row->asset) }}" class="font-medium text-gray-900 hover:text-blue-600">{{ $row->asset->name }}</a>
                        <span class="block text-xs text-gray-400">{{ $row->asset->code }}</span>
                    </td>
                    <td class="px-5 py-3 text-gray-600">{{ $row->asset->category?->name }}</td>
                    <td class="px-5 py-3 text-gray-600">{{ $row->asset->acquisition_date?->format('d/m/Y') }}</td>
                    <td class="px-5 py-3 text-center text-xs">{{ __(ucfirst(str_replace('_', ' ', $row->asset->status))) }}</td>
                    <td class="px-5 py-3 text-right font-mono">{{ $money($row->cost) }}</td>
                    <td class="px-5 py-3 text-right font-mono text-gray-600">{{ $money($row->accumulated) }}</td>
                    <td class="px-5 py-3 text-right font-mono font-semibold">{{ $money($row->book_value) }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-5 py-12 text-center text-gray-500">{{ __('No assets held on this date.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="bg-gray-50 font-semibold">
                <tr>
                    <td colspan="4" class="px-5 py-3 text-right">{{ __('Total') }}</td>
                    <td class="px-5 py-3 text-right font-mono">{{ $money($totals['cost']) }}</td>
                    <td class="px-5 py-3 text-right font-mono">{{ $money($totals['accumulated']) }}</td>
                    <td class="px-5 py-3 text-right font-mono">{{ $money($totals['book_value']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
