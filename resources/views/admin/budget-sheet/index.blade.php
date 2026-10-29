@extends('layouts.admin')
@section('title', __('Income & Expenditure'))
@section('breadcrumb', __('Budgets > Income & Expenditure'))

@section('content')
<div class="space-y-5">
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">{{ __('Income & Expenditure sheets') }}</h2>
            <p class="text-sm text-gray-500 mt-0.5 max-w-3xl">{{ __('The school\'s annual budget, line by line, with what actually happened beside what was planned. The actual column is read from the fees, incomes, expenses and salaries already recorded — nothing on it is typed.') }}</p>
        </div>
        @can('budget-line.view')
            <a href="{{ route('admin.budget-line.index') }}" class="text-sm text-gray-600 hover:text-gray-800 border border-gray-300 rounded-lg px-3 py-2 whitespace-nowrap">{{ __('Budget Lines') }}</a>
        @endcan
    </div>

    @unless($linesReady)
        <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-900 flex flex-col sm:flex-row sm:items-center gap-3">
            <p class="flex-1">{{ __('The sheet has no lines yet, so there is nothing to budget against.') }}</p>
            @can('budget-line.edit')
                <a href="{{ route('admin.budget-line.index') }}" class="font-medium underline underline-offset-2 whitespace-nowrap">{{ __('Set up the lines') }}</a>
            @endcan
        </div>
    @endunless

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            @if($sheets->isEmpty())
                <p class="p-8 text-center text-sm text-gray-500">{{ __('No sheet yet. Start one for the current year on the right.') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-5 py-2 text-left">{{ __('Sheet') }}</th>
                            <th class="px-5 py-2 text-left">{{ __('Period') }}</th>
                            <th class="px-5 py-2 text-right">{{ __('Budgeted spend') }}</th>
                            <th class="px-5 py-2 text-right">{{ __('Spent') }}</th>
                            <th class="px-5 py-2 text-left">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($sheets as $sheet)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3"><a href="{{ route('admin.budget-sheet.show', $sheet) }}" class="font-medium text-teal-700 hover:text-teal-900">{{ $sheet->title }}</a><span class="block text-xs text-gray-400">{{ $sheet->fiscal_year }}</span></td>
                                <td class="px-5 py-3 text-gray-600">{{ $sheet->start_date->format('d M Y') }} — {{ $sheet->end_date->format('d M Y') }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $sheet->total_amount) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $sheet->spent_amount) }}</td>
                                <td class="px-5 py-3"><span class="text-xs px-2 py-0.5 rounded-full {{ $sheet->status === 'active' ? 'bg-emerald-100 text-emerald-700' : ($sheet->status === 'draft' ? 'bg-gray-100 text-gray-600' : 'bg-sky-100 text-sky-700') }}">{{ str_replace('_', ' ', $sheet->status) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        @can('budget-sheet.edit')
        <form method="POST" action="{{ route('admin.budget-sheet.store') }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 space-y-3" x-data="{ seed: '{{ $sheets->isNotEmpty() ? 'actual' : '' }}' }">
            @csrf
            <h3 class="font-semibold text-gray-800">{{ __('Start a sheet') }}</h3>
            <div>
                <label class="block text-xs text-gray-600 mb-1">{{ __('Title') }}</label>
                <input type="text" name="title" required maxlength="191" value="{{ old('title', __('Income & Expenditure :year', ['year' => $suggested['start']->format('Y').($suggested['start']->format('Y') !== $suggested['end']->format('Y') ? '/'.$suggested['end']->format('Y') : '')])) }}" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">{{ __('From') }}</label>
                    <input type="date" name="start_date" required value="{{ old('start_date', $suggested['start']->format('Y-m-d')) }}" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">{{ __('To') }}</label>
                    <input type="date" name="end_date" required value="{{ old('end_date', $suggested['end']->format('Y-m-d')) }}" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">{{ __('Opening balance') }}</label>
                <input type="number" step="0.01" name="opening_balance" value="{{ old('opening_balance') }}" placeholder="{{ __('Carried from the previous sheet if left blank') }}" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
            </div>
            @if($sheets->isNotEmpty())
                <div class="border-t border-gray-100 pt-3 space-y-2 text-sm">
                    <p class="text-xs font-medium text-gray-700">{{ __('Fill the figures from') }}</p>
                    <label class="flex items-center gap-2"><input type="radio" name="seed_from" value="actual" x-model="seed"> {{ __('What was actually spent and received last year') }}</label>
                    <label class="flex items-center gap-2"><input type="radio" name="seed_from" value="budget" x-model="seed"> {{ __('What was planned last year') }}</label>
                    <label class="flex items-center gap-2"><input type="radio" name="seed_from" value="" x-model="seed"> {{ __('Nothing — start empty') }}</label>
                    <div x-show="seed" class="flex items-center gap-2">
                        <input type="number" step="0.1" name="uplift_percent" value="{{ old('uplift_percent', 0) }}" class="w-24 text-sm border border-gray-300 rounded-lg px-3 py-1.5">
                        <span class="text-xs text-gray-500">{{ __('% uplift') }}</span>
                    </div>
                </div>
            @endif
            <button type="submit" {{ $linesReady ? '' : 'disabled' }} class="w-full px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-medium hover:bg-teal-700 disabled:opacity-40">{{ __('Create sheet') }}</button>
        </form>
        @endcan
    </div>
</div>
@endsection
