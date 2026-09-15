@extends('layouts.admin')

@section('title', __('Cash Flow Statement'))
@section('breadcrumb', __('Accounting > Reports > Cash Flow Statement'))

@php
    $currency = \App\Models\SchoolSetting::current()?->currency ?? 'FCFA';
    $current = $comparative ? $report['current'] : $report;
    $previous = $comparative ? $report['previous'] : null;
    $money = fn ($v) => number_format((float) $v, 2);
    $exportQuery = request()->query() + ['comparative' => $comparative ? 1 : null];
@endphp

@section('content')
<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-700">
                {{ $comparative ? __('Comparative Cash Flow Statement') : __('Cash Flow Statement') }}
                <span class="text-sm text-gray-400">(Tableau des flux de trésorerie)</span>
            </h3>
            <p class="text-sm text-gray-500">
                {{ __(':from to :to', ['from' => \Illuminate\Support\Carbon::parse($current['from'])->format('d/m/Y'), 'to' => \Illuminate\Support\Carbon::parse($current['to'])->format('d/m/Y')]) }}
                @if($comparative)
                · {{ __('compared with :from to :to', ['from' => \Illuminate\Support\Carbon::parse($previous['from'])->format('d/m/Y'), 'to' => \Illuminate\Support\Carbon::parse($previous['to'])->format('d/m/Y')]) }}
                @endif
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route($comparative ? 'admin.accounting-reports.cash-flow-statement' : 'admin.accounting-reports.comparative-cash-flow', request()->query()) }}"
               class="border border-gray-300 hover:bg-gray-50 px-3 py-2 rounded-lg text-sm">
                {{ $comparative ? __('Single period') : __('Compare with previous period') }}
            </a>
            <a href="{{ route('admin.accounting-reports.cash-flow-export', $exportQuery + ['format' => 'pdf']) }}" class="border border-gray-300 hover:bg-gray-50 px-3 py-2 rounded-lg text-sm">PDF</a>
            <a href="{{ route('admin.accounting-reports.cash-flow-export', $exportQuery + ['format' => 'csv']) }}" class="border border-gray-300 hover:bg-gray-50 px-3 py-2 rounded-lg text-sm">CSV</a>
        </div>
    </div>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Fiscal Year') }}</label>
            <select name="fiscal_year_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">{{ __('Use the dates') }}</option>
                @foreach($fiscalYears as $fy)
                <option value="{{ $fy->id }}" @selected((string) request('fiscal_year_id') === (string) $fy->id)>{{ $fy->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('From') }}</label>
            <input type="date" name="start_date" value="{{ request('start_date') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('To') }}</label>
            <input type="date" name="end_date" value="{{ request('end_date') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('View') }}</button>
    </form>

    @unless($current['balanced'])
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
        {{ __('The statement is out by :amount. Every non-cash account is included, so this can only come from a posted journal entry whose debits and credits do not match. Check the trial balance.', ['amount' => $money($current['difference'])]) }}
    </div>
    @endunless

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-5 py-3 text-left"></th>
                    <th class="px-5 py-3 text-right">{{ $comparative ? __('Current') : __('Amount') }}</th>
                    @if($comparative)
                    <th class="px-5 py-3 text-right">{{ __('Previous') }}</th>
                    <th class="px-5 py-3 text-right">{{ __('Variance') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($current['sections'] as $key => $section)
                <tr class="bg-gray-50/60"><td colspan="{{ $comparative ? 4 : 2 }}" class="px-5 pt-4 pb-2 font-semibold text-gray-700">{{ $section['title'] }}</td></tr>
                @forelse($section['items'] as $item)
                @php $prior = $comparative ? collect($previous['sections'][$key]['items'])->firstWhere('key', $item['key']) : null; @endphp
                <tr class="border-b border-gray-100">
                    <td class="px-5 py-2 pl-8 text-gray-700">{{ $item['label'] }}</td>
                    <td class="px-5 py-2 text-right font-mono {{ $item['amount'] < 0 ? 'text-red-600' : 'text-gray-800' }}">{{ $money($item['amount']) }}</td>
                    @if($comparative)
                    <td class="px-5 py-2 text-right font-mono text-gray-500">{{ $money($prior['amount'] ?? 0) }}</td>
                    <td class="px-5 py-2 text-right font-mono text-gray-500">{{ $money($report['variance'][$key][$item['key']] ?? 0) }}</td>
                    @endif
                </tr>
                @empty
                <tr><td colspan="{{ $comparative ? 4 : 2 }}" class="px-5 py-2 pl-8 text-gray-400">{{ __('No movements.') }}</td></tr>
                @endforelse
                <tr class="border-b border-gray-200">
                    <td class="px-5 py-2 pl-8 font-semibold text-gray-700">{{ __('Net cash from this activity') }}</td>
                    <td class="px-5 py-2 text-right font-mono font-semibold">{{ $money($section['total']) }}</td>
                    @if($comparative)
                    <td class="px-5 py-2 text-right font-mono font-semibold text-gray-500">{{ $money($previous['sections'][$key]['total']) }}</td>
                    <td class="px-5 py-2 text-right font-mono font-semibold text-gray-500">{{ $money($report['variance'][$key]['_total']) }}</td>
                    @endif
                </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t-2 border-gray-300">
                @foreach([
                    [__('Net change in cash'), 'net_change'],
                    [__('Cash at the start of the period'), 'opening_cash'],
                    [__('Cash at the end of the period'), 'closing_cash'],
                ] as [$label, $field])
                <tr>
                    <td class="px-5 py-2 font-semibold text-gray-800">{{ $label }}</td>
                    <td class="px-5 py-2 text-right font-mono font-bold">{{ $money($current[$field]) }} {{ $currency }}</td>
                    @if($comparative)
                    <td class="px-5 py-2 text-right font-mono text-gray-500">{{ $money($previous[$field]) }}</td>
                    <td class="px-5 py-2 text-right font-mono text-gray-500">{{ $money($current[$field] - $previous[$field]) }}</td>
                    @endif
                </tr>
                @endforeach
            </tfoot>
        </table>
    </div>

    @if($current['balanced'])
    <p class="text-xs text-gray-400">{{ __('Reconciled: opening cash plus the net change equals the cash accounts at the end of the period.') }}</p>
    @endif
</div>
@endsection
