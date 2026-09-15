@extends('layouts.admin')

@section('title', $template->title)
@section('breadcrumb', __('Accounting > Recurring Entries'))

@section('content')
<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.recurring-entries.index') }}" class="text-xs text-gray-400 hover:text-gray-600">&larr; {{ __('Recurring Entries') }}</a>
            <h3 class="text-lg font-semibold text-gray-700 mt-1">{{ $template->title }}</h3>
            <p class="text-sm text-gray-500">
                {{ __(\App\Models\RecurringJournalEntry::FREQUENCIES[$template->frequency] ?? $template->frequency) }}
                · {{ __('from :date', ['date' => $template->start_date->format('d/m/Y')]) }}
                @if($template->end_date) · {{ __('until :date', ['date' => $template->end_date->format('d/m/Y')]) }} @endif
                · {{ $template->auto_post ? __('posts automatically') : __('left as draft') }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('recurring-entry.process')
            @if($template->is_active)
            <form method="POST" action="{{ route('admin.recurring-entries.process', $template) }}">@csrf
                <button type="submit" class="border border-gray-300 hover:bg-gray-50 px-3 py-2 rounded-lg text-sm">{{ __('Run now') }}</button>
            </form>
            @endif
            @endcan
            @can('recurring-entry.edit')
            @if($template->is_active)
            <form method="POST" action="{{ route('admin.recurring-entries.skip-next', $template) }}"
                  onsubmit="return confirm('{{ __('Skip the next run? No entry will be generated for it.') }}')">@csrf
                <button type="submit" class="border border-gray-300 hover:bg-gray-50 px-3 py-2 rounded-lg text-sm">{{ __('Skip next') }}</button>
            </form>
            <form method="POST" action="{{ route('admin.recurring-entries.pause', $template) }}">@csrf
                <button type="submit" class="border border-gray-300 hover:bg-gray-50 px-3 py-2 rounded-lg text-sm">{{ __('Pause') }}</button>
            </form>
            @else
            <form method="POST" action="{{ route('admin.recurring-entries.resume', $template) }}" class="flex gap-2">@csrf
                <input type="date" name="next_run_date" value="{{ max(today()->toDateString(), $template->next_run_date->toDateString()) }}"
                       class="px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg text-sm font-medium">{{ __('Resume') }}</button>
            </form>
            @endif
            <a href="{{ route('admin.recurring-entries.edit', $template) }}" class="border border-gray-300 hover:bg-gray-50 px-3 py-2 rounded-lg text-sm">{{ __('Edit') }}</a>
            @endcan
            @can('recurring-entry.create')
            <form method="POST" action="{{ route('admin.recurring-entries.duplicate', $template) }}">@csrf
                <button type="submit" class="border border-gray-300 hover:bg-gray-50 px-3 py-2 rounded-lg text-sm">{{ __('Duplicate') }}</button>
            </form>
            @endcan
            @can('recurring-entry.delete')
            @if($template->runs_generated === 0)
            <form method="POST" action="{{ route('admin.recurring-entries.destroy', $template) }}"
                  onsubmit="return confirm('{{ __('Delete this recurring entry?') }}')">@csrf @method('DELETE')
                <button type="submit" class="border border-red-200 text-red-600 hover:bg-red-50 px-3 py-2 rounded-lg text-sm">{{ __('Delete') }}</button>
            </form>
            @endif
            @endcan
        </div>
    </div>

    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-sm text-emerald-700 font-medium">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700 font-medium">{{ session('error') }}</div>
    @endif

    @unless($template->isBalanced())
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
        {{ __('This template does not balance, so every run will fail until it is corrected.') }}
    </div>
    @endunless

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Account') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Description') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Debit') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Credit') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($template->lines as $line)
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ $line->account?->account_code }} — {{ $line->account?->account_name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $line->description }}</td>
                        <td class="px-4 py-3 text-sm text-right font-mono">{{ (float) $line->debit ? number_format($line->debit, 2) : '' }}</td>
                        <td class="px-4 py-3 text-sm text-right font-mono">{{ (float) $line->credit ? number_format($line->credit, 2) : '' }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t border-gray-200 bg-gray-50">
                    <tr>
                        <td colspan="2" class="px-4 py-2 text-right text-sm font-semibold">{{ __('Totals') }}</td>
                        <td class="px-4 py-2 text-right text-sm font-bold font-mono">{{ number_format($template->totalDebit(), 2) }}</td>
                        <td class="px-4 py-2 text-right text-sm font-bold font-mono">{{ number_format($template->totalCredit(), 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ __('Schedule') }}</h4>
            <dl class="text-sm space-y-1 mb-3">
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('Status') }}</dt><dd>{{ $template->is_active ? __('Active') : __('Paused') }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('Last run') }}</dt><dd>{{ $template->last_run_date?->format('d/m/Y') ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('Runs so far') }}</dt><dd>{{ $template->runs_generated }}</dd></div>
            </dl>
            @if($schedule->isNotEmpty())
            <p class="text-xs font-medium text-gray-400 uppercase mb-1">{{ __('Next runs') }}</p>
            <ul class="text-sm text-gray-700 space-y-1">
                @foreach($schedule as $date)<li>{{ $date->format('d/m/Y') }}</li>@endforeach
            </ul>
            @else
            <p class="text-sm text-gray-400">{{ __('Nothing scheduled.') }}</p>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <h4 class="text-sm font-semibold text-gray-700 px-4 pt-4">{{ __('Generated entries') }}</h4>
        <table class="w-full mt-2">
            <tbody class="divide-y divide-gray-200">
                @forelse($generated as $entry)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-sm font-mono text-gray-700">{{ $entry->entry_number }}</td>
                    <td class="px-4 py-2 text-sm text-gray-600">{{ $entry->entry_date->format('d/m/Y') }}</td>
                    <td class="px-4 py-2">
                        @if($entry->is_posted)<span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">{{ __('Posted') }}</span>
                        @else<span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ __('Draft') }}</span>@endif
                    </td>
                    <td class="px-4 py-2 text-right"><a href="{{ route('admin.journal-entries.show', $entry) }}" class="text-blue-600 hover:text-blue-800 text-sm">{{ __('View') }}</a></td>
                </tr>
                @empty
                <tr><td class="px-4 py-6 text-center text-sm text-gray-400">{{ __('Nothing generated yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
