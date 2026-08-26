@extends('layouts.admin')

@section('title', __('Fiscal Years'))
@section('breadcrumb', __('Accounting > Fiscal Years'))

@section('content')
<div class="space-y-4">
    <h3 class="text-lg font-semibold text-gray-700">{{ __('Fiscal Years') }} <span class="text-sm text-gray-400">(Exercices Comptables)</span></h3>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h4 class="text-base font-semibold text-gray-700 mb-3">{{ __('Add Fiscal Year') }}</h4>
        <form method="POST" action="{{ route('admin.fiscal-years.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Name') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="2025/2026" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Start Date') }}</label>
                <input type="date" name="start_date" value="{{ old('start_date') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('End Date') }}</label>
                <input type="date" name="end_date" value="{{ old('end_date') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
            </div>
            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">+ {{ __('Add Fiscal Year') }}</button>
        </form>
        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        @error('end_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Name') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Start') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('End') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Periods') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($fiscalYears as $fy)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $fy->name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $fy->start_date->format('d/m/Y') }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $fy->end_date->format('d/m/Y') }}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-600">{{ $fy->periods_count }}</td>
                    <td class="px-4 py-3">
                        @if($fy->is_closed)<span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-slate-200 text-slate-700">{{ __('Closed') }}</span>
                        @elseif($fy->is_active)<span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">{{ __('Active') }}</span>
                        @else<span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ __('Inactive') }}</span>@endif
                    </td>
                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                        @unless($fy->is_active || $fy->is_closed)
                        <form method="POST" action="{{ route('admin.fiscal-years.set-active', $fy) }}" class="inline">@csrf<button class="text-green-600 hover:text-green-800 text-xs font-medium">{{ __('Set Active') }}</button></form>
                        @endunless
                        @unless($fy->is_closed)
                        <form method="POST" action="{{ route('admin.fiscal-years.close', $fy) }}" class="inline" onsubmit="return confirm('{{ __('Close this fiscal year?') }}')">@csrf<button class="text-amber-600 hover:text-amber-800 text-xs font-medium">{{ __('Close') }}</button></form>
                        @endunless
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">{{ __('No data found') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
