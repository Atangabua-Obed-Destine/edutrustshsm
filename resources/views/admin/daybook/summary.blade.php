@extends('layouts.admin')
@section('title', __('Monthly Summary'))
@section('breadcrumb', __('Finance > Cash Book > Monthly Summary'))

@section('content')
@php
    $sectionLabels = \App\Models\BudgetLine::sectionLabels();
    $fmt = fn ($v) => $v == 0 ? '—' : number_format((float) $v);
@endphp
<div class="space-y-5">
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">{{ __('Monthly Summary') }}</h2>
            <p class="text-sm text-gray-500 mt-0.5 max-w-3xl">{{ __('The month turned into a budget report: what was planned for the year, what happened this month, what had happened before it, and what is left. Read a row left to right and it tells the whole story of one line.') }}</p>
        </div>
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs text-gray-600 mb-1">{{ __('Sheet') }}</label>
                <select name="budget" onchange="this.form.submit()" style="appearance:auto;" class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white">
                    @foreach($sheets as $s)
                        <option value="{{ $s->id }}" @selected($budget?->id === $s->id)>{{ $s->title }}</option>
                    @endforeach
                </select>
            </div>
            @if($budget)
            <div>
                <label class="block text-xs text-gray-600 mb-1">{{ __('Month') }}</label>
                <select name="month" onchange="this.form.submit()" style="appearance:auto;" class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white">
                    @foreach($months as $m)
                        <option value="{{ $m->format('Y-m') }}" @selected($m->format('Y-m') === $month->format('Y-m'))>{{ $m->translatedFormat('F Y') }}</option>
                    @endforeach
                </select>
            </div>
            @endif
        </form>
    </div>

    @if(! $budget)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center text-sm text-gray-500">
            {{ __('There is no Income & Expenditure sheet yet. The monthly summary reads its budget column from one.') }}
            @can('budget-sheet.edit') <a href="{{ route('admin.budget-sheet.index') }}" class="text-teal-700 underline underline-offset-2 ml-1">{{ __('Start a sheet') }}</a> @endcan
        </div>
    @else
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <a href="{{ route('admin.budget-sheet.show', $budget) }}" class="border border-gray-300 rounded-lg px-3 py-2 text-gray-700 hover:bg-gray-50">{{ __('Open the sheet') }}</a>
            <a href="{{ route('admin.daybook.index', ['month' => $month->format('Y-m')]) }}" class="border border-gray-300 rounded-lg px-3 py-2 text-gray-700 hover:bg-gray-50">{{ __('The entries behind this month') }}</a>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="text-left py-2 px-3 w-14">{{ __('Code') }}</th>
                            <th class="text-left py-2 px-3">{{ __('Description') }}</th>
                            <th class="text-right py-2 px-3" title="{{ __('The figure set for this line for the whole year, from the Income & Expenditure sheet. A dash means no figure was ever entered.') }}">{{ __('Budget') }}</th>
                            <th class="text-right py-2 px-3" title="{{ __('What this line took or spent in this month alone.') }}">{{ __('Monthly') }}</th>
                            <th class="text-right py-2 px-3" title="{{ __('Everything on this line since the sheet opened, up to the end of last month.') }}">{{ __('B/F') }}</th>
                            <th class="text-right py-2 px-3" title="{{ __('Monthly plus B/F — the year to date. At year end this must match the sheet.') }}">{{ __('Cumulative') }}</th>
                            <th class="text-right py-2 px-3" title="{{ __('Budget minus cumulative. On expenditure this is headroom left; on income it is what is still expected.') }}">{{ __('Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(['income', 'expenditure', 'capital'] as $section)
                            <tr><td colspan="7" class="py-1.5 px-3 text-[11px] font-semibold uppercase tracking-wider bg-slate-800 text-white">{{ $sectionLabels[$section] }}</td></tr>
                            @foreach($summary['lines'] as $row)
                                @continue($row['line']->section !== $section)
                                @php
                                    $line = $row['line'];
                                    $hasBudget = $row['budget'] != 0;
                                    $balanceBad = $section === 'income' ? false : ($hasBudget && $row['balance'] < 0);
                                @endphp
                                <tr class="{{ $line->is_header ? 'bg-slate-50 font-semibold' : 'hover:bg-teal-50/40' }} border-b border-gray-100">
                                    <td class="py-1.5 px-3 font-mono text-xs text-gray-500">{{ $line->code }}</td>
                                    <td class="py-1.5 px-3 {{ $line->is_header ? 'uppercase' : 'pl-8' }}">{{ $line->displayName() }}</td>
                                    <td class="py-1.5 px-3 text-right tabular-nums {{ $hasBudget ? '' : 'text-gray-300' }}" title="{{ $hasBudget ? '' : __('No budget set for this line') }}">{{ $fmt($row['budget']) }}</td>
                                    <td class="py-1.5 px-3 text-right tabular-nums">{{ $fmt($row['monthly']) }}</td>
                                    <td class="py-1.5 px-3 text-right tabular-nums text-gray-500">{{ $fmt($row['brought']) }}</td>
                                    <td class="py-1.5 px-3 text-right tabular-nums font-medium">{{ $fmt($row['cumulative']) }}</td>
                                    <td class="py-1.5 px-3 text-right tabular-nums {{ $balanceBad ? 'text-red-600 font-semibold' : ($hasBudget ? ($section === 'income' && $row['balance'] <= 0 ? 'text-emerald-700' : 'text-gray-700') : 'text-gray-300') }}">{{ $hasBudget ? number_format($row['balance']) : '—' }}</td>
                                </tr>
                                @if(isset($summary['group_ends'][$line->id]))
                                    @php $h = $summary['lines'][$summary['group_ends'][$line->id]->id]; @endphp
                                    <tr class="bg-gray-50 text-xs text-gray-500 italic border-b border-gray-200">
                                        <td></td><td class="py-1 px-3 pl-8">{{ __('Total :name', ['name' => $h['line']->displayName()]) }}</td>
                                        <td class="py-1 px-3 text-right tabular-nums">{{ $fmt($h['budget']) }}</td>
                                        <td class="py-1 px-3 text-right tabular-nums">{{ $fmt($h['monthly']) }}</td>
                                        <td class="py-1 px-3 text-right tabular-nums">{{ $fmt($h['brought']) }}</td>
                                        <td class="py-1 px-3 text-right tabular-nums">{{ $fmt($h['cumulative']) }}</td>
                                        <td class="py-1 px-3 text-right tabular-nums">{{ $h['budget'] != 0 ? number_format($h['balance']) : '—' }}</td>
                                    </tr>
                                @endif
                            @endforeach
                            @php $t = $summary['totals'][$section]; @endphp
                            <tr class="bg-slate-100 font-semibold border-y-2 border-slate-300">
                                <td></td><td class="py-2 px-3">{{ __('Total :section', ['section' => $sectionLabels[$section]]) }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ number_format($t['budget']) }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ number_format($t['monthly']) }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ number_format($t['brought']) }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ number_format($t['cumulative']) }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ number_format($t['budget'] - $t['cumulative']) }}</td>
                            </tr>
                        @endforeach
                        @php $net = $summary['totals']['income']['cumulative'] - $summary['totals']['expenditure']['cumulative']; $netM = $summary['totals']['income']['monthly'] - $summary['totals']['expenditure']['monthly']; @endphp
                        <tr class="bg-slate-800 text-white font-semibold">
                            <td></td><td class="py-2 px-3">{{ __('NET BALANCE') }} <span class="text-xs font-normal opacity-70">({{ __('income − expenditure') }})</span></td>
                            <td class="py-2 px-3 text-right tabular-nums">{{ number_format($summary['totals']['income']['budget'] - $summary['totals']['expenditure']['budget']) }}</td>
                            <td class="py-2 px-3 text-right tabular-nums">{{ number_format($netM) }}</td>
                            <td class="py-2 px-3 text-right tabular-nums">{{ number_format($summary['totals']['income']['brought'] - $summary['totals']['expenditure']['brought']) }}</td>
                            <td class="py-2 px-3 text-right tabular-nums">{{ number_format($net) }}</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="px-4 py-2 text-xs text-gray-400 border-t border-gray-100">{{ __('Salaries are at their full cost here and on the sheet (gross plus employer charges); the cash book itemises the net amount paid.') }}</p>
        </div>

        @if($summary['unallocated'] > 0)
            <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-900">
                {{ __(':amount moved this month but sits on no line, so it appears in none of the rows above.', ['amount' => number_format($summary['unallocated'])]) }}
                @can('budget-line.edit') <a href="{{ route('admin.budget-line.index') }}" class="font-medium underline underline-offset-2 ml-1">{{ __('Fix on Budget Lines') }}</a> @endcan
            </div>
        @endif

        {{-- Profit centres --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-800">{{ __('Trading activities') }}</h3>
            <p class="text-xs text-gray-500 mt-0.5 mb-3">{{ __('Lines that share a profit-centre name, set side by side so the margin is visible. They are already counted in the sections above; nothing here is added to any total.') }}</p>
            @if(empty($summary['profit_centres']))
                <p class="text-sm text-gray-400">{{ __('No trading activities are configured yet. Give an income line and an expenditure line the same profit centre — Canteen, say — and the margin for that activity appears here.') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead class="text-[11px] uppercase tracking-wide text-gray-500"><tr><th class="text-left py-1">{{ __('Activity') }}</th><th class="text-right py-1">{{ __('Earned') }}</th><th class="text-right py-1">{{ __('Cost') }}</th><th class="text-right py-1">{{ __('Margin') }}</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($summary['profit_centres'] as $name => $c)
                            <tr>
                                <td class="py-1.5 font-medium text-gray-800">{{ $name }}</td>
                                <td class="py-1.5 text-right tabular-nums text-emerald-700">{{ number_format($c['income']) }}</td>
                                <td class="py-1.5 text-right tabular-nums text-red-600">{{ number_format($c['expenditure']) }}</td>
                                <td class="py-1.5 text-right tabular-nums font-semibold {{ $c['margin'] < 0 ? 'text-red-600' : 'text-gray-800' }}">{{ number_format($c['margin']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif
</div>
@endsection
