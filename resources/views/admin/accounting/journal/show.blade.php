@extends('layouts.admin')

@section('title', __('Journal Entry'))
@section('breadcrumb', __('Accounting > Journal Entries > :n', ['n' => $entry->entry_number]))

@section('content')
<div class="space-y-4 max-w-4xl">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-start justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <h3 class="text-lg font-semibold text-gray-800 font-mono">{{ $entry->entry_number }}</h3>
                    @if($entry->is_posted)<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs bg-green-100 text-green-800">{{ __('Posted') }}</span>
                    @else<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ __('Draft') }}</span>@endif
                    @if($entry->is_system_generated)<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs bg-blue-50 text-blue-600">{{ __('Auto-generated') }}</span>@endif
                </div>
                <p class="text-sm text-gray-500 mt-1">{{ $entry->entry_date->format('d/m/Y') }} · {{ ucfirst($entry->journal_type) }} · {{ $entry->description }}</p>
            </div>
            <div class="flex gap-2">
                @if($entry->is_posted)
                    <form method="POST" action="{{ route('admin.journal-entries.unpost', $entry) }}" onsubmit="return confirm('{{ __('Un-post this entry?') }}')">@csrf<button class="bg-amber-500 hover:bg-amber-600 text-white px-3 py-1.5 rounded-lg text-sm">{{ __('Un-post') }}</button></form>
                @else
                    <form method="POST" action="{{ route('admin.journal-entries.post', $entry) }}">@csrf<button class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-sm">{{ __('Post') }}</button></form>
                    @unless($entry->is_system_generated)
                    <form method="POST" action="{{ route('admin.journal-entries.destroy', $entry) }}" onsubmit="return confirm('{{ __('Delete this entry?') }}')">@csrf @method('DELETE')<button class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg text-sm">{{ __('Delete') }}</button></form>
                    @endunless
                @endif
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
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
                @foreach($entry->lines as $line)
                <tr>
                    <td class="px-4 py-2.5 text-sm text-gray-800"><span class="font-mono">{{ $line->account->account_code }}</span> {{ $line->account->account_name }}</td>
                    <td class="px-4 py-2.5 text-sm text-gray-600">{{ $line->description }}</td>
                    <td class="px-4 py-2.5 text-sm text-right">{{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
                    <td class="px-4 py-2.5 text-sm text-right">{{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 border-t border-gray-200">
                <tr>
                    <td colspan="2" class="px-4 py-3 text-sm font-semibold text-right">{{ __('Totals') }}</td>
                    <td class="px-4 py-3 text-sm text-right font-bold">{{ number_format($entry->total_debit, 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right font-bold">{{ number_format($entry->total_credit, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
