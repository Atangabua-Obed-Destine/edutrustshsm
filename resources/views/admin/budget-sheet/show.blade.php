@extends('layouts.admin')
@section('title', $budget->title)
@section('breadcrumb', __('Budgets > Income & Expenditure > :title', ['title' => $budget->title]))

@section('content')
@php
    $unallocatedTotal = array_sum($unallocated);
    $sectionLabels = \App\Models\BudgetLine::sectionLabels();
    $canEdit = auth()->user()->can('budget-sheet.edit');
    $status = $budget->status;
@endphp
<style>
    .sheet tr.section td { background: #1e293b; color: #fff; }
    .sheet tr.header td { background: #f1f5f9; }
    .sheet tr.subtotal td { background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .sheet tr.total td { background: #f8fafc; border-top: 2px solid #cbd5e1; border-bottom: 2px solid #cbd5e1; }
    .sheet tr.line:hover td { background: #f0fdfa; }
    .sheet td, .sheet th { border-bottom: 1px solid #f1f5f9; }
</style>
<div class="space-y-5">

    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div>
            <a href="{{ route('admin.budget-sheet.index') }}" class="text-xs text-gray-500 hover:text-gray-800">&larr; {{ __('All sheets') }}</a>
            <h2 class="text-xl font-bold text-gray-800 mt-1">{{ $budget->title }}</h2>
            <p class="text-sm text-gray-500">
                {{ $budget->start_date->format('d M Y') }} — {{ $budget->end_date->format('d M Y') }}
                · <span class="px-2 py-0.5 rounded-full text-xs {{ $status === 'active' ? 'bg-emerald-100 text-emerald-700' : ($status === 'draft' ? 'bg-gray-100 text-gray-600' : 'bg-sky-100 text-sky-700') }}">{{ str_replace('_', ' ', $status) }}</span>
                @if($previous) · {{ __('compared with :title', ['title' => $previous->title]) }} @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-sm">
            @can('budget-sheet.export')
                <a href="{{ route('admin.budget-sheet.pdf', $budget) }}" class="border border-gray-300 rounded-lg px-3 py-2 text-gray-700 hover:bg-gray-50">{{ __('PDF') }}</a>
                <a href="{{ route('admin.budget-sheet.csv', $budget) }}" class="border border-gray-300 rounded-lg px-3 py-2 text-gray-700 hover:bg-gray-50">{{ __('CSV') }}</a>
            @endcan
            @can('daybook.view')
                <a href="{{ route('admin.daybook.summary', ['budget' => $budget->id]) }}" class="border border-gray-300 rounded-lg px-3 py-2 text-gray-700 hover:bg-gray-50">{{ __('Monthly Summary') }}</a>
            @endcan
            @can('budget.approve')
                @if($status === 'draft')
                    <form method="POST" action="{{ route('admin.budget.submit', $budget) }}">@csrf<button class="rounded-lg px-3 py-2 bg-sky-600 text-white hover:bg-sky-700">{{ __('Submit for approval') }}</button></form>
                @elseif($status === 'pending_approval')
                    <form method="POST" action="{{ route('admin.budget.approve', $budget) }}">@csrf<button class="rounded-lg px-3 py-2 bg-sky-600 text-white hover:bg-sky-700">{{ __('Approve') }}</button></form>
                @elseif($status === 'approved')
                    <form method="POST" action="{{ route('admin.budget.activate', $budget) }}">@csrf<button class="rounded-lg px-3 py-2 bg-emerald-600 text-white hover:bg-emerald-700">{{ __('Activate') }}</button></form>
                @elseif($status === 'active')
                    <form method="POST" action="{{ route('admin.budget.close', $budget) }}" onsubmit="return confirm('{{ __('Close this sheet? Its figures become the record of the year.') }}')">@csrf<button class="rounded-lg px-3 py-2 border border-gray-300 text-gray-700 hover:bg-gray-50">{{ __('Close') }}</button></form>
                @endif
            @endcan
        </div>
    </div>

    {{-- Money the sheet cannot place --}}
    @if($unallocatedTotal > 0)
        <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-900 flex flex-col sm:flex-row sm:items-center gap-3">
            <p class="flex-1">
                <span class="font-semibold">{{ __(':amount moved in this period but sits on no line.', ['amount' => number_format($unallocatedTotal)]) }}</span>
                {{ collect($unallocated)->map(fn ($v, $k) => __($k).': '.number_format($v))->join(' · ') }}
                — {{ __('it is counted nowhere above until the category behind it points at a budget line.') }}
            </p>
            @can('budget-line.edit')
                <a href="{{ route('admin.budget-line.index') }}" class="font-medium underline underline-offset-2 whitespace-nowrap">{{ __('Fix on Budget Lines') }}</a>
            @endcan
        </div>
    @endif

    {{-- Four figures the head asks for --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach([['income', __('Income'), 'emerald'], ['expenditure', __('Expenditure'), 'red'], ['closing', __('Closing balance'), 'gray'], ['capital', __('Capital'), 'gray']] as [$key, $label, $tone])
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">{{ $label }}</p>
                <p class="text-xl font-bold text-gray-800 mt-1 tabular-nums">{{ number_format($totals['actual'][$key]) }}</p>
                <p class="text-xs text-gray-400 tabular-nums">{{ __('budget :amount', ['amount' => number_format($totals['budget'][$key])]) }}</p>
            </div>
        @endforeach
    </div>

    {{-- The sheet --}}
    <form method="POST" action="{{ route('admin.budget-sheet.figures', $budget) }}" id="figures-form" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        @csrf
        <div class="px-5 py-3 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <p class="text-xs text-gray-500">
                @if($editable && $canEdit)
                    {{ __('Type the budget for each line. Headings total their lines; the actual column is read from the money already recorded.') }}
                @elseif($editable)
                    {{ __('This sheet is still a draft.') }}
                @else
                    {{ __('Figures are locked once a sheet leaves draft. Use a revision to change the total.') }}
                @endif
            </p>
            @if($editable && $canEdit)
                <button type="submit" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-medium hover:bg-teal-700 whitespace-nowrap">{{ __('Save figures') }}</button>
            @endif
        </div>
        <div class="overflow-x-auto">
            @include('admin.budget-sheet._table', ['print' => false, 'editable' => $editable && $canEdit])
        </div>
    </form>

    {{-- One form per tuition line, outside the figures form so nothing nests --}}
    @if($editable && $canEdit)
        @foreach($forecastRates as $lineId => $r)
            <form id="forecast-{{ $lineId }}" method="POST" action="{{ route('admin.budget-sheet.forecast', $budget) }}" class="hidden">
                @csrf
                <input type="hidden" name="budget_line_id" value="{{ $lineId }}">
            </form>
        @endforeach
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- Does the sheet agree with the ledger? --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-800">{{ __('Does this sheet agree with the ledger?') }}</h3>
            <p class="text-xs text-gray-500 mt-0.5 mb-3">{{ __('The same money, added up two ways: down the sheet by budget line, and down the ledger by account class. A difference is a category mapped for one and not the other.') }}</p>
            <table class="w-full text-sm">
                <thead class="text-[11px] uppercase tracking-wide text-gray-500"><tr><th class="text-left py-1">{{ __('Section') }}</th><th class="text-right py-1">{{ __('This sheet') }}</th><th class="text-right py-1">{{ __('Ledger') }}</th><th class="text-right py-1">{{ __('Difference') }}</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($ledger as $section => $row)
                        <tr>
                            <td class="py-1.5">{{ $sectionLabels[$section] }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ number_format($row['sheet']) }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ number_format($row['ledger']) }}</td>
                            <td class="py-1.5 text-right tabular-nums {{ abs($row['difference']) < 1 ? 'text-emerald-700' : 'text-amber-700' }}">{{ abs($row['difference']) < 1 ? __('agrees') : number_format($row['difference']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if(collect($ledger)->sum('ledger') == 0 && collect($ledger)->sum('sheet') != 0)
                <p class="text-xs text-gray-500 mt-2">{{ __('The ledger holds nothing for this period: no category is mapped to an account yet, so nothing has been posted. The sheet still reads the money directly.') }}</p>
            @endif
        </div>

        {{-- Period, and the way out --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 space-y-4">
            @if($canEdit)
                <form method="POST" action="{{ route('admin.budget-sheet.period', $budget) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div><label class="block text-xs text-gray-600 mb-1">{{ __('From') }}</label><input type="date" name="start_date" value="{{ $budget->start_date->format('Y-m-d') }}" class="text-sm border border-gray-300 rounded-lg px-3 py-1.5"></div>
                    <div><label class="block text-xs text-gray-600 mb-1">{{ __('To') }}</label><input type="date" name="end_date" value="{{ $budget->end_date->format('Y-m-d') }}" class="text-sm border border-gray-300 rounded-lg px-3 py-1.5"></div>
                    <button type="submit" class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">{{ __('Change period') }}</button>
                    <p class="w-full text-xs text-gray-400">{{ __('A sheet created with the wrong dates reports the wrong year\'s money. Changing them re-reads the actuals.') }}</p>
                </form>
                @if($status === 'draft')
                    <form method="POST" action="{{ route('admin.budget-sheet.destroy', $budget) }}" onsubmit="return confirm('{{ __('Delete this draft sheet and its figures?') }}')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-sm text-red-600 hover:text-red-800">{{ __('Delete this draft') }}</button>
                    </form>
                @endif
            @endif
            <p class="text-xs text-gray-500">{{ __('Revisions, the approval trail and the departmental budgets that sit under this sheet are on the budget record.') }} <a href="{{ route('admin.budget.show', ['budget' => $budget, 'record' => 1]) }}" class="text-teal-700 underline underline-offset-2">{{ __('Open the budget record') }}</a></p>
        </div>
    </div>
</div>
@endsection
