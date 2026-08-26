@extends('layouts.admin')

@section('title', __('Journal Entries'))
@section('breadcrumb', __('Accounting > Journal Entries'))

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-700">{{ __('Journal Entries') }} <span class="text-sm text-gray-400">(Écritures Comptables)</span></h3>
        <a href="{{ route('admin.journal-entries.create') }}" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">+ {{ __('Add Journal Entry') }}</a>
    </div>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Fiscal Year') }}</label>
            <select name="fiscal_year_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">{{ __('All') }}</option>
                @foreach($fiscalYears as $fy)<option value="{{ $fy->id }}" {{ (string)request('fiscal_year_id') === (string)$fy->id ? 'selected' : '' }}>{{ $fy->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Status') }}</label>
            <select name="status" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">{{ __('All') }}</option>
                <option value="posted" {{ request('status') === 'posted' ? 'selected' : '' }}>{{ __('Posted') }}</option>
                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>{{ __('Draft') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Start Date') }}</label>
            <input type="date" name="start_date" value="{{ request('start_date') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('End Date') }}</label>
            <input type="date" name="end_date" value="{{ request('end_date') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Filter') }}</button>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Entry #') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Date') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Type') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Description') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Total Debit') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Total Credit') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($entries as $e)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-mono text-gray-700">{{ $e->entry_number }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $e->entry_date->format('d/m/Y') }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 capitalize">{{ $e->journal_type }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $e->description }} @if($e->is_system_generated)<span class="text-xs text-blue-500">[auto]</span>@endif</td>
                    <td class="px-4 py-3 text-sm text-right">{{ number_format($e->total_debit, 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right">{{ number_format($e->total_credit, 2) }}</td>
                    <td class="px-4 py-3">
                        @if($e->is_posted)<span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">{{ __('Posted') }}</span>
                        @else<span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ __('Draft') }}</span>@endif
                    </td>
                    <td class="px-4 py-3 text-right"><a href="{{ route('admin.journal-entries.show', $e) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">{{ __('View') }}</a></td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">{{ __('No data found') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $entries->links() }}</div>
</div>
@endsection
