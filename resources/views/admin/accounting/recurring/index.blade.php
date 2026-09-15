@extends('layouts.admin')

@section('title', __('Recurring Entries'))
@section('breadcrumb', __('Accounting > Recurring Entries'))

@section('content')
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-700">{{ __('Recurring Entries') }}</h3>
            <p class="text-sm text-gray-500">{{ __('Journal entries that repeat on a schedule — rent, standing charges, monthly accruals') }}</p>
        </div>
        <div class="flex gap-2">
            @can('recurring-entry.process')
            @if($summary['due'] > 0)
            <form method="POST" action="{{ route('admin.recurring-entries.process-all') }}">
                @csrf
                <button type="submit" class="border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-800 px-4 py-2 rounded-lg text-sm font-medium">
                    {{ __('Run :count due now', ['count' => $summary['due']]) }}
                </button>
            </form>
            @endif
            @endcan
            @can('recurring-entry.create')
            <a href="{{ route('admin.recurring-entries.create') }}" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">+ {{ __('New Recurring Entry') }}</a>
            @endcan
        </div>
    </div>

    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-sm text-emerald-700 font-medium">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700 font-medium">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-400 uppercase">{{ __('Active') }}</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $summary['active'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-400 uppercase">{{ __('Paused') }}</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $summary['paused'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-400 uppercase">{{ __('Due now') }}</p>
            <p class="text-2xl font-bold {{ $summary['due'] > 0 ? 'text-amber-600' : 'text-gray-300' }} mt-1">{{ $summary['due'] }}</p>
        </div>
    </div>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Search') }}</label>
            <input type="text" name="search" value="{{ request('search') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Status') }}</label>
            <select name="status" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">{{ __('All') }}</option>
                <option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option>
                <option value="paused" @selected(request('status') === 'paused')>{{ __('Paused') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Frequency') }}</label>
            <select name="frequency" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">{{ __('All') }}</option>
                @foreach($frequencies as $value => $label)
                <option value="{{ $value }}" @selected(request('frequency') === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Filter') }}</button>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Title') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Frequency') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Amount') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Next Run') }}</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">{{ __('Runs') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($templates as $template)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm">
                        <div class="font-medium text-gray-800">{{ $template->title }}</div>
                        @if($template->description)<div class="text-xs text-gray-400">{{ $template->description }}</div>@endif
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ __($frequencies[$template->frequency] ?? $template->frequency) }}</td>
                    <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format($template->totalDebit(), 2) }}</td>
                    <td class="px-4 py-3 text-sm {{ $template->is_active && $template->next_run_date->lte(today()) ? 'text-amber-700 font-medium' : 'text-gray-600' }}">
                        {{ $template->is_active ? $template->next_run_date->format('d/m/Y') : '—' }}
                    </td>
                    <td class="px-4 py-3 text-sm text-center text-gray-600">{{ $template->runs_generated }}</td>
                    <td class="px-4 py-3">
                        @if($template->is_active)
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">{{ __('Active') }}</span>
                        @else
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ __('Paused') }}</span>
                        @endif
                        @unless($template->isBalanced())
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-700">{{ __('Not balanced') }}</span>
                        @endunless
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.recurring-entries.show', $template) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">{{ __('View') }}</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">{{ __('No recurring entries yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $templates->links() }}</div>

    @if($upcoming->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ __('Coming up in the next 30 days') }}</h4>
        <ul class="divide-y divide-gray-100 text-sm">
            @foreach($upcoming->take(15) as $run)
            <li class="py-2 flex justify-between gap-3">
                <span class="text-gray-600">{{ $run->date->format('d/m/Y') }}</span>
                <a href="{{ route('admin.recurring-entries.show', $run->template) }}" class="flex-1 text-gray-800 hover:text-blue-600">{{ $run->template->title }}</a>
                <span class="font-mono text-gray-600">{{ number_format($run->template->totalDebit(), 2) }}</span>
            </li>
            @endforeach
        </ul>
    </div>
    @endif
</div>
@endsection
