{{--
    The sheet itself, shared by the screen and the PDF. Code · Description ·
    Last year · Budget · Actual · Variance, one half of the sheet at a time,
    a heading's group closed with its own subtotal, each half closed with its
    total, then the balances exactly as the paper lays them out.

    $print = true drops the inputs and the forecast helpers.
--}}
@php
    $sectionLabels = \App\Models\BudgetLine::sectionLabels();
    $fmt = fn ($v) => $v == 0 ? '—' : number_format((float) $v);
    $variance = function (float $budget, float $actual, string $section) {
        $diff = $budget - $actual;
        // Income beating its budget is good; expenditure beating its budget is an overspend.
        $good = $section === 'income' ? $actual >= $budget : $diff >= 0;
        return ['value' => $diff, 'good' => $good];
    };
    $print = $print ?? false;
    $editing = ! $print && $editable;
    $colspan = $previous ? 6 : 5;
@endphp
<table class="sheet w-full text-sm">
    <thead>
        <tr class="text-[11px] uppercase tracking-wide text-gray-500">
            <th class="text-left py-2 px-2 w-14">{{ __('Code') }}</th>
            <th class="text-left py-2 px-2">{{ __('Description') }}</th>
            @if($previous)<th class="text-right py-2 px-2 w-28">{{ $previous->fiscal_year }}</th>@endif
            <th class="text-right py-2 px-2 w-32">{{ __('Budget') }}</th>
            <th class="text-right py-2 px-2 w-28">{{ __('Actual') }}</th>
            <th class="text-right py-2 px-2 w-28">{{ __('Variance') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach(['income', 'expenditure', 'capital'] as $section)
            @php $rows = $lines->where('section', $section); @endphp
            @if($section === 'capital')
                {{-- The closing balance sits between expenditure and capital, as on the paper. --}}
                <tr class="total"><td></td><td class="py-2 px-2 font-semibold">{{ __('Closing balance') }} <span class="text-xs font-normal text-gray-500">({{ __('opening + income − expenditure') }})</span></td>
                    @if($previous)<td class="text-right px-2 tabular-nums">{{ number_format($priorTotals['closing']) }}</td>@endif
                    <td class="text-right px-2 tabular-nums font-semibold">{{ number_format($totals['budget']['closing']) }}</td>
                    <td class="text-right px-2 tabular-nums font-semibold">{{ number_format($totals['actual']['closing']) }}</td>
                    <td></td></tr>
            @endif
            <tr class="section"><td colspan="{{ $colspan }}" class="py-1.5 px-2 text-[11px] font-semibold uppercase tracking-wider">{{ $sectionLabels[$section] }}</td></tr>
            @if($section === 'income')
                <tr class="total"><td></td><td class="py-2 px-2 font-medium">{{ __('Opening balance') }}</td>
                    @if($previous)<td class="text-right px-2 tabular-nums">{{ number_format($priorTotals['opening']) }}</td>@endif
                    <td class="text-right px-2">
                        @if($editing)<input type="number" step="0.01" name="opening_balance" value="{{ (float) $budget->opening_balance }}" class="w-full text-right text-sm border border-gray-300 rounded px-2 py-1 tabular-nums">@else<span class="tabular-nums">{{ number_format($totals['budget']['opening']) }}</span>@endif
                    </td>
                    <td class="text-right px-2 tabular-nums">{{ number_format($totals['actual']['opening']) }}</td><td></td></tr>
            @endif
            @foreach($rows as $line)
                @php
                    $id = $line->id;
                    $b = (float) ($budgeted[$id] ?? 0); $a = (float) ($actual[$id] ?? 0); $p = (float) ($priorActual[$id] ?? 0);
                    $v = $variance($b, $a, $section);
                    $forecast = $forecasts[$id] ?? null;
                    $rate = $forecastRates[$id] ?? null;
                @endphp
                <tr class="{{ $line->is_header ? 'header' : 'line' }}">
                    <td class="py-1.5 px-2 font-mono text-xs text-gray-500 align-top">{{ $line->code }}</td>
                    <td class="py-1.5 px-2 {{ $line->is_header ? 'font-semibold' : 'pl-6' }} align-top">
                        {{ $line->is_header ? strtoupper($line->displayName()) : $line->displayName() }}
                        @if(! $print && $line->is_payroll)<span class="ml-1 text-[11px] text-amber-700">{{ __('payroll') }}</span>@endif
                        @if($forecast)
                            <div class="text-[11px] text-gray-500 mt-0.5">{{ __(':count pupils at :rate', ['count' => $forecast->student_count, 'rate' => number_format((float) $forecast->rate)]) }}
                                @if($rate && abs($rate['rate'] - (float) $forecast->rate) > 0.5 && $rate['rate'] > 0)
                                    <span class="text-amber-700">· {{ __('fees now imply :rate', ['rate' => number_format($rate['rate'])]) }}</span>
                                @endif
                            </div>
                        @endif
                        @if($editing && $rate && ! $line->is_header)
                            <details class="mt-1 text-[11px] text-gray-500">
                                <summary class="cursor-pointer text-teal-700">{{ __('Budget from headcount') }} · {{ __(':count pupils now, :rate per year', ['count' => $rate['students'], 'rate' => number_format($rate['rate'])]) }}</summary>
                                <div class="mt-1.5 flex flex-wrap items-end gap-2" data-forecast="{{ $id }}">
                                    <label class="block">{{ __('Pupils') }}<input type="number" min="0" name="student_count" value="{{ $forecast->student_count ?? $rate['students'] }}" form="forecast-{{ $id }}" class="block w-20 text-sm border border-gray-300 rounded px-2 py-1"></label>
                                    <label class="block">{{ __('Fee per year') }}<input type="number" min="0" step="0.01" name="rate" value="{{ (float) ($forecast->rate ?? $rate['rate']) }}" form="forecast-{{ $id }}" class="block w-32 text-sm border border-gray-300 rounded px-2 py-1"></label>
                                    <button type="submit" form="forecast-{{ $id }}" class="px-2.5 py-1 rounded bg-gray-800 text-white text-xs">{{ __('Use') }}</button>
                                    <span class="text-gray-400">{{ collect($rate['forms'])->map(fn ($f) => $f['name'].': '.$f['students'].' × '.number_format($f['fee']))->join(' · ') }}</span>
                                </div>
                            </details>
                        @endif
                    </td>
                    @if($previous)<td class="py-1.5 px-2 text-right tabular-nums text-gray-500 align-top">{{ $fmt($p) }}</td>@endif
                    <td class="py-1.5 px-2 text-right align-top">
                        @if($editing && ! $line->is_header)
                            <input type="number" min="0" step="0.01" name="amounts[{{ $id }}]" value="{{ $b ?: '' }}" class="w-full text-right text-sm border border-gray-300 rounded px-2 py-1 tabular-nums" placeholder="0">
                        @else
                            <span class="tabular-nums {{ $line->is_header ? 'font-semibold' : '' }}">{{ $fmt($b) }}</span>
                        @endif
                    </td>
                    <td class="py-1.5 px-2 text-right tabular-nums align-top {{ $line->is_header ? 'font-semibold' : '' }}">{{ $fmt($a) }}</td>
                    <td class="py-1.5 px-2 text-right tabular-nums align-top {{ $b == 0 && $a == 0 ? 'text-gray-300' : ($v['good'] ? 'text-emerald-700' : 'text-red-600') }}">{{ $b == 0 && $a == 0 ? '—' : number_format($v['value']) }}</td>
                </tr>
                @if(isset($groupEnds[$id]))
                    @php $h = $groupEnds[$id]; $hb = (float) ($budgeted[$h->id] ?? 0); $ha = (float) ($actual[$h->id] ?? 0); $hv = $variance($hb, $ha, $section); @endphp
                    <tr class="subtotal">
                        <td></td><td class="py-1 px-2 pl-6 text-xs text-gray-500 italic">{{ __('Total :name', ['name' => $h->displayName()]) }}</td>
                        @if($previous)<td class="py-1 px-2 text-right tabular-nums text-xs text-gray-500">{{ $fmt($priorActual[$h->id] ?? 0) }}</td>@endif
                        <td class="py-1 px-2 text-right tabular-nums text-xs font-medium">{{ $fmt($hb) }}</td>
                        <td class="py-1 px-2 text-right tabular-nums text-xs font-medium">{{ $fmt($ha) }}</td>
                        <td class="py-1 px-2 text-right tabular-nums text-xs {{ $hv['good'] ? 'text-emerald-700' : 'text-red-600' }}">{{ $hb == 0 && $ha == 0 ? '—' : number_format($hv['value']) }}</td>
                    </tr>
                @endif
            @endforeach
            @php $tv = $variance($totals['budget'][$section], $totals['actual'][$section], $section); @endphp
            <tr class="total">
                <td></td><td class="py-2 px-2 font-semibold">{{ __('Total :section', ['section' => $sectionLabels[$section]]) }}</td>
                @if($previous)<td class="text-right px-2 tabular-nums">{{ number_format($priorTotals[$section]) }}</td>@endif
                <td class="text-right px-2 tabular-nums font-semibold">{{ number_format($totals['budget'][$section]) }}</td>
                <td class="text-right px-2 tabular-nums font-semibold">{{ number_format($totals['actual'][$section]) }}</td>
                <td class="text-right px-2 tabular-nums {{ $tv['good'] ? 'text-emerald-700' : 'text-red-600' }}">{{ number_format($tv['value']) }}</td>
            </tr>
        @endforeach
        <tr class="total"><td></td><td class="py-2 px-2 font-semibold">{{ __('Cash position') }} <span class="text-xs font-normal text-gray-500">({{ __('closing balance − capital') }})</span></td>
            @if($previous)<td class="text-right px-2 tabular-nums">{{ number_format($priorTotals['cash']) }}</td>@endif
            <td class="text-right px-2 tabular-nums font-semibold">{{ number_format($totals['budget']['cash']) }}</td>
            <td class="text-right px-2 tabular-nums font-semibold">{{ number_format($totals['actual']['cash']) }}</td>
            <td></td></tr>
    </tbody>
</table>
