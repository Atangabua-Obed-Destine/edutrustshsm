@extends('layouts.admin')

@section('title', __('Trends & Analysis'))
@section('breadcrumb', __('Finance > Cash Book > Trends & Analysis'))

@section('content')
@php
    $fmt = fn ($n) => number_format((float) $n, 0);
    $a = $analysis;
    $c = $collection;
    $pacing = $a['pacing'];
    $note = fn (string $read, string $do) => '<div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3 text-xs text-gray-500 leading-relaxed"><p><span class="font-semibold text-gray-600">'.e(__('How to read it')).'</span> · '.e($read).'</p><p><span class="font-semibold text-gray-600">'.e(__('What to do with it')).'</span> · '.e($do).'</p></div>';
@endphp
<div class="space-y-4">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-700">{{ __('Trends & Analysis') }} · {{ $year['label'] }}</h3>
            <p class="text-sm text-gray-500">{{ __(':start to :end. A month of the book says what happened; this says whether the year is going well, in the order a meeting asks.', ['start' => $year['start']->format('d M Y'), 'end' => $year['end']->format('d M Y')]) }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.daybook.analysis', ['month' => $previousYear]) }}" class="text-sm border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-2 rounded-lg" aria-label="{{ __('Previous year') }}">←</a>
            <a href="{{ route('admin.daybook.analysis', ['month' => $nextYear]) }}" class="text-sm border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-2 rounded-lg" aria-label="{{ __('Next year') }}">→</a>
            <a href="{{ route('admin.daybook.index', ['month' => now()->between($year['start'], $year['end']) ? now()->format('Y-m') : $year['start']->format('Y-m')]) }}" class="text-sm border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-2 rounded-lg">{{ __('Back to the book') }}</a>
            @can('daybook.export')
                @if($a['has_data'])
                <form method="POST" action="{{ route('admin.daybook.analysis.pdf', ['month' => $year['start']->format('Y-m')]) }}" id="meeting-pack">
                    @csrf
                    <input type="hidden" name="charts[cash]"><input type="hidden" name="charts[monthly]"><input type="hidden" name="charts[pacing]">
                    <button type="submit" class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg">{{ __('Meeting pack (PDF)') }}</button>
                </form>
                @endif
            @endcan
        </div>
    </div>

    {{-- Can these figures be taken into a meeting? Said first, before any of them. --}}
    <div class="rounded-xl border p-4 {{ $concerns ? 'bg-amber-50 border-amber-200' : 'bg-emerald-50 border-emerald-200' }}">
        <p class="text-sm font-semibold {{ $concerns ? 'text-amber-900' : 'text-emerald-900' }}">
            {{ $concerns
                ? trans_choice('{1} One thing to check before these figures go into a meeting.|[2,*] :count things to check before these figures go into a meeting.', $concerns, ['count' => $concerns])
                : __('These figures are safe to take into a meeting.') }}
        </p>
        <ul class="mt-2 space-y-1.5">
            @foreach($checks as $check)
                <li class="flex items-start gap-2 text-sm">
                    <span class="mt-0.5 shrink-0 font-bold {{ $check['ok'] ? 'text-emerald-600' : 'text-amber-600' }}" aria-hidden="true">{{ $check['ok'] ? '✓' : '!' }}</span>
                    <span class="{{ $check['ok'] ? 'text-gray-600' : 'text-gray-800' }}">
                        <span class="font-medium">{{ $check['label'] }}.</span> {{ $check['detail'] }}
                        @if($check['route'] && Route::has($check['route']))
                            <a href="{{ route($check['route']) }}" class="text-blue-600 hover:underline whitespace-nowrap">{{ __('Fix it') }} →</a>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    </div>

    @if(! $a['has_data'])
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center text-sm text-gray-500">
            {{ __('No money has moved in this year yet, so there is no pattern to show. The charts appear as soon as money is recorded.') }}
        </div>
    @else

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        @foreach([
            [__('Opening balance'), $a['opening'], 'text-gray-800', $sheet ? __('from :sheet', ['sheet' => $sheet->title]) : __('no sheet: counted from zero')],
            [__('Money in'), $a['total_in'], 'text-emerald-700', null],
            [__('Money out'), $a['total_out'], 'text-red-700', null],
            [__('Net for the year'), $a['net'], $a['net'] < 0 ? 'text-red-700' : 'text-gray-800', null],
            [__('Lowest month-end'), $a['lowest']['balance'] ?? 0, ($a['lowest']['balance'] ?? 0) < 0 ? 'text-red-700' : 'text-gray-800', $a['lowest']['label'] ?? null],
        ] as [$label, $value, $tone, $sub])
        <div class="bg-white rounded-xl border border-gray-200 p-4 {{ $loop->last ? 'col-span-2 lg:col-span-1' : '' }}">
            <p class="text-xs text-gray-500 uppercase tracking-wide">{{ $label }}</p>
            <p class="text-xl font-bold {{ $tone }}">{{ $fmt($value) }} <span class="text-xs font-normal text-gray-400">{{ $currency }}</span></p>
            @if($sub)<p class="text-xs text-gray-400 mt-0.5">{{ $sub }}</p>@endif
        </div>
        @endforeach
    </div>

    {{-- 1. Where the cash stood --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h4 class="text-sm font-semibold text-gray-700">{{ __('Cash position, month by month') }}</h4>
        <div class="mt-3 h-64"><canvas id="chart-cash" role="img" aria-label="{{ __('Cash position, month by month') }}"></canvas></div>
        {!! $note(__('The balance at the end of each month, starting from the opening cash. Each point is where the cash actually stood.'), __('A dip below zero, or close to it, is the month to plan for: hold back spending or bring fee collection forward before it comes round again.')) !!}
    </div>

    {{-- 2. In and out --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h4 class="text-sm font-semibold text-gray-700">{{ __('Money in and money out, month by month') }}</h4>
        <div class="mt-3 h-64"><canvas id="chart-monthly" role="img" aria-label="{{ __('Money in and money out, month by month') }}"></canvas></div>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="text-gray-500 uppercase"><tr>
                    <th class="py-1.5 pr-3 text-left">{{ __('Month') }}</th>
                    <th class="py-1.5 px-3 text-right">{{ __('In') }}</th><th class="py-1.5 px-3 text-right">{{ __('Of which fees') }}</th>
                    <th class="py-1.5 px-3 text-right">{{ __('Out') }}</th><th class="py-1.5 px-3 text-right">{{ __('Net') }}</th><th class="py-1.5 pl-3 text-right">{{ __('Balance') }}</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($a['months'] as $m)
                    <tr>
                        <td class="py-1.5 pr-3 whitespace-nowrap"><a href="{{ route('admin.daybook.index', ['month' => $m['month']]) }}" class="text-blue-600 hover:underline">{{ $m['label'] }}</a></td>
                        <td class="py-1.5 px-3 text-right text-emerald-700 tabular-nums">{{ $m['in'] ? $fmt($m['in']) : '—' }}</td>
                        <td class="py-1.5 px-3 text-right text-gray-500 tabular-nums">{{ $m['fees'] ? $fmt($m['fees']) : '—' }}</td>
                        <td class="py-1.5 px-3 text-right text-red-700 tabular-nums">{{ $m['out'] ? $fmt($m['out']) : '—' }}</td>
                        <td class="py-1.5 px-3 text-right tabular-nums {{ $m['net'] < 0 ? 'text-red-700 font-semibold' : 'text-gray-700' }}">{{ $fmt($m['net']) }}</td>
                        <td class="py-1.5 pl-3 text-right tabular-nums font-medium {{ $m['balance'] < 0 ? 'text-red-700' : 'text-gray-800' }}">{{ $fmt($m['balance']) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {!! $note(__('Green is money in, red is money out; a month in deficit is shown in red in the table. Fees arrive in waves at the start of each term, so a deficit in a quiet month is normal.'), __('A deficit in a month when fees should be coming in is worth a question: is collection behind, or did a large payment fall in that month?')) !!}
    </div>

    {{-- 3. Spending against the year --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h4 class="text-sm font-semibold text-gray-700">{{ __('Spending against the year') }}</h4>
        @if(! $pacing)
            <p class="mt-2 text-sm text-gray-500">{{ __('No Income & Expenditure sheet covers this year, so there is no budget to measure spending against.') }}
                @if(Route::has('admin.budget-sheet.index'))<a href="{{ route('admin.budget-sheet.index') }}" class="text-blue-600 hover:underline">{{ __('Set one up') }} →</a>@endif
            </p>
        @else
            @php
                $tone = ['good' => 'bg-emerald-50 text-emerald-800 border-emerald-200', 'warning' => 'bg-amber-50 text-amber-800 border-amber-200', 'critical' => 'bg-red-50 text-red-800 border-red-200', 'incomplete' => 'bg-slate-50 text-slate-700 border-slate-200', 'none' => 'bg-slate-50 text-slate-700 border-slate-200'][$pacing['status']];
                $verdict = [
                    'good' => __('Spending is keeping pace with the year.'),
                    'warning' => __('Spending is running a little ahead of the year.'),
                    'critical' => __('Spending is running well ahead of the year: an overspend is forming.'),
                    'incomplete' => __('The sheet looks unfinished, so the comparison is not reliable yet: only :covered of the :lines lines that spent money have a budget figure.', ['covered' => $pacing['lines_budgeted'], 'lines' => $pacing['lines_spending']]),
                    'none' => __('The sheet has no expenditure figures yet.'),
                ][$pacing['status']];
            @endphp
            <div class="mt-3 rounded-lg border px-3 py-2 text-sm {{ $tone }}">{{ $verdict }}</div>
            <div class="mt-3 grid grid-cols-1 lg:grid-cols-3 gap-4 items-center">
                <div class="lg:col-span-2 h-40"><canvas id="chart-pacing" role="img" aria-label="{{ __('Spending against the year') }}"></canvas></div>
                <dl class="text-sm space-y-1.5">
                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('Year gone') }}</dt><dd class="font-medium">{{ number_format($pacing['elapsed'], 0) }}%</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('Budget spent') }}</dt><dd class="font-medium">{{ number_format($pacing['consumed'], 0) }}%</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('Spent') }}</dt><dd class="tabular-nums">{{ $fmt($pacing['spent']) }} / {{ $fmt($pacing['budgeted']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('At this rate, the year ends at') }}</dt><dd class="tabular-nums font-medium">{{ $fmt($pacing['projected']) }}</dd></div>
                    @if($pacing['budgeted_in'] > 0)
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('Income received') }}</dt><dd class="tabular-nums">{{ number_format($pacing['received_share'], 0) }}% {{ __('of budget') }}</dd></div>
                    @endif
                </dl>
            </div>
            {!! $note(__('Two bars: how much of the year has gone, and how much of the spending budget has. When the second runs ahead of the first, money is going faster than the year.'), __('Ahead by more than a few points: look at the largest spending headings below before the next payment run. The projection assumes the current rate simply continues.')) !!}
        @endif
    </div>

    {{-- 4. What the money rests on --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @foreach([
            [__('Where the money comes from'), $a['top_in'], 'bg-emerald-500', $a['concentration']['in'], __('The school depends on these. A heading that is most of the income is the one whose shortfall hurts.')],
            [__('Where the money goes'), $a['top_out'], 'bg-red-500', $a['concentration']['out'], __('The headings any saving has to come from: small headings cannot balance a year.')],
        ] as [$title, $rows, $bar, $conc, $why])
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h4 class="text-sm font-semibold text-gray-700">{{ $title }}</h4>
            @if($conc['count'] > 0)
                <p class="mt-1 text-xs text-gray-500">{{ __('The top :count headings are :share% of it.', ['count' => $conc['count'], 'share' => number_format($conc['share'], 0)]) }} {{ $why }}</p>
            @endif
            <div class="mt-3">
            @forelse($rows as $r)
                <div class="flex items-center justify-between text-sm py-1.5 border-b border-gray-50 last:border-0">
                    <span class="text-gray-700">{{ $r['heading'] }}</span>
                    <span class="flex items-center gap-3">
                        <span class="text-xs text-gray-400 w-10 text-right">{{ number_format($r['share'], 0) }}%</span>
                        <span class="w-24 h-1.5 bg-gray-100 rounded-full overflow-hidden"><span class="block h-1.5 {{ $bar }}" style="width: {{ round($r['share']) }}%"></span></span>
                        <span class="font-medium text-gray-800 w-28 text-right tabular-nums">{{ $fmt($r['amount']) }}</span>
                    </span>
                </div>
            @empty
                <p class="text-sm text-gray-500">{{ __('Nothing yet.') }}</p>
            @endforelse
            </div>
        </div>
        @endforeach
    </div>

    {{-- 5. What changed --}}
    @if($a['movers'])
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
            <h4 class="text-sm font-semibold text-gray-700">{{ __('What changed, :from to :to', ['from' => $a['movers']['from'], 'to' => $a['movers']['to']]) }}</h4>
            <p class="text-xs text-gray-500">{{ __('Against the last month that had any movement, so a quiet holiday month does not make everything look new.') }}</p>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase"><tr>
                <th class="px-4 py-2 text-left">{{ __('Heading') }}</th>
                <th class="px-4 py-2 text-right">{{ $a['movers']['from'] }}</th><th class="px-4 py-2 text-right">{{ $a['movers']['to'] }}</th><th class="px-4 py-2 text-right">{{ __('Change') }}</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($a['movers']['rows'] as $r)
                <tr>
                    <td class="px-4 py-2 text-gray-800">{{ $r['heading'] }} <span class="text-xs {{ $r['direction'] === 'in' ? 'text-emerald-600' : 'text-red-600' }}">{{ $r['direction'] === 'in' ? __('in') : __('out') }}</span></td>
                    <td class="px-4 py-2 text-right tabular-nums text-gray-500">{{ $fmt($r['previous']) }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $fmt($r['current']) }}</td>
                    <td class="px-4 py-2 text-right tabular-nums font-medium">{{ $r['change'] > 0 ? '+' : '−' }}{{ $fmt(abs($r['change'])) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- 6. The numbers a meeting asks about --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
            <h4 class="text-sm font-semibold text-gray-700">{{ __('Largest single movements') }}</h4>
            <p class="text-xs text-gray-500">{{ __('Almost every question in a meeting is about one of these.') }}</p>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-100">
                @foreach($a['largest'] as $row)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 whitespace-nowrap text-gray-600">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                    <td class="px-4 py-2 font-mono text-xs text-blue-600">{{ $row['ref'] }}</td>
                    <td class="px-4 py-2 text-gray-800">{{ $row['description'] }}</td>
                    <td class="px-4 py-2 text-gray-500">{{ $row['heading'] }}</td>
                    <td class="px-4 py-2 text-right font-medium tabular-nums {{ $row['direction'] === 'in' ? 'text-emerald-700' : 'text-red-700' }}">{{ $row['direction'] === 'in' ? '+' : '−' }}{{ $fmt($row['amount']) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>

    {{-- 7. What the sheet cannot place --}}
    @if($a['unanalysed']['count'] > 0)
    <div class="bg-white rounded-xl shadow-sm border border-amber-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-amber-100 bg-amber-50">
            <h4 class="text-sm font-semibold text-amber-900">{{ __('Money on no budget line') }}</h4>
            <p class="text-xs text-amber-800">{{ __(':amount, :share% of all the money that moved. It is in the book, but the Income & Expenditure sheet leaves it out, so the sheet understates the year by this much.', ['amount' => $fmt($a['unanalysed']['amount']), 'share' => number_format($a['unanalysed']['share'], 0)]) }}
                @if(Route::has('admin.account-mappings.index'))<a href="{{ route('admin.account-mappings.index') }}" class="underline whitespace-nowrap">{{ __('Point the categories at lines') }}</a>@endif
            </p>
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-100">
                @foreach($a['unanalysed']['rows'] as $row)
                <tr>
                    <td class="px-4 py-2 whitespace-nowrap text-gray-600">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                    <td class="px-4 py-2 font-mono text-xs text-blue-600">{{ $row['ref'] }}</td>
                    <td class="px-4 py-2 text-gray-800">{{ $row['description'] }}</td>
                    <td class="px-4 py-2 text-gray-500">{{ $row['heading'] }}</td>
                    <td class="px-4 py-2 text-right tabular-nums font-medium text-amber-800">{{ $fmt($row['unanalysed']) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @endif

    {{-- 8. Who has paid: a position, not a flow, so it stands apart from the book --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h4 class="text-sm font-semibold text-gray-700">{{ __('Who has paid, and who has not') }}</h4>
        <p class="text-xs text-gray-500">{{ __('Where this year\'s fees stand today, counting students rather than fees: a student with one fee still owed has paid in part.') }}</p>

        @if(! ($c['has_data'] ?? false))
            <p class="mt-3 text-sm text-gray-500">{{ __('No fees have been billed for this year yet.') }}</p>
        @else
            @php $p = $c['pupils']; @endphp
            <div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach([
                    [__('Paid in full'), $p['full'], 'bg-emerald-50 text-emerald-800'],
                    [__('Paid in part'), $p['part'], 'bg-amber-50 text-amber-800'],
                    [__('Paid nothing'), $p['nothing'], 'bg-red-50 text-red-800'],
                    [__('Collected'), number_format($p['rate'], 0).'%', 'bg-slate-50 text-slate-800'],
                ] as [$label, $value, $tone])
                    <div class="rounded-lg p-3 {{ $tone }}">
                        <p class="text-xs uppercase tracking-wide opacity-80">{{ $label }}</p>
                        <p class="text-xl font-bold">{{ $value }}</p>
                        @if($loop->index < 3)<p class="text-xs opacity-70">{{ __('of :count students', ['count' => $p['pupils']]) }}</p>@else<p class="text-xs opacity-70">{{ $fmt($p['collected']) }} / {{ $fmt($p['billed']) }}</p>@endif
                    </div>
                @endforeach
            </div>
            @if($p['pupils'] > 0)
                <div class="mt-3 flex h-2.5 rounded-full overflow-hidden bg-gray-100" role="img" aria-label="{{ __(':full paid in full, :part in part, :nothing nothing', ['full' => $p['full'], 'part' => $p['part'], 'nothing' => $p['nothing']]) }}">
                    <span class="bg-emerald-500" style="width: {{ $p['full'] / $p['pupils'] * 100 }}%"></span>
                    <span class="bg-amber-400" style="width: {{ $p['part'] / $p['pupils'] * 100 }}%"></span>
                    <span class="bg-red-400" style="width: {{ $p['nothing'] / $p['pupils'] * 100 }}%"></span>
                </div>
            @endif
            @if($c['arrears']['owed'] > 0)
                <p class="mt-3 text-sm text-amber-800 bg-amber-50 rounded-lg px-3 py-2">{{ trans_choice('{1} On top of this, :count student still owes :amount from an earlier year.|[2,*] On top of this, :count students still owe :amount from earlier years.', $c['arrears']['pupils'], ['count' => $c['arrears']['pupils'], 'amount' => $fmt($c['arrears']['owed'])]) }}</p>
            @endif

            <div class="mt-5 grid grid-cols-1 xl:grid-cols-2 gap-5">
                <div class="overflow-x-auto">
                    <h5 class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">{{ __('By class') }}</h5>
                    <table class="w-full text-sm">
                        <thead class="text-xs text-gray-500"><tr>
                            <th class="py-1.5 text-left">{{ __('Class') }}</th><th class="py-1.5 text-right">{{ __('Students') }}</th>
                            <th class="py-1.5 text-right">{{ __('Full') }}</th><th class="py-1.5 text-right">{{ __('Part') }}</th><th class="py-1.5 text-right">{{ __('None') }}</th>
                            <th class="py-1.5 text-right">{{ __('Outstanding') }}</th><th class="py-1.5 text-right">{{ __('Rate') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($c['by_class'] as $class => $t)
                            <tr>
                                <td class="py-1.5 text-gray-800">{{ $class }}</td><td class="py-1.5 text-right tabular-nums">{{ $t['pupils'] }}</td>
                                <td class="py-1.5 text-right tabular-nums text-emerald-700">{{ $t['full'] }}</td><td class="py-1.5 text-right tabular-nums text-amber-700">{{ $t['part'] }}</td><td class="py-1.5 text-right tabular-nums text-red-700">{{ $t['nothing'] }}</td>
                                <td class="py-1.5 text-right tabular-nums">{{ $fmt($t['outstanding']) }}</td><td class="py-1.5 text-right tabular-nums font-medium">{{ number_format($t['rate'], 0) }}%</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="overflow-x-auto">
                    <h5 class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">{{ __('By fee') }}</h5>
                    <table class="w-full text-sm">
                        <thead class="text-xs text-gray-500"><tr>
                            <th class="py-1.5 text-left">{{ __('Fee') }}</th><th class="py-1.5 text-right">{{ __('Billed') }}</th><th class="py-1.5 text-right">{{ __('Collected') }}</th><th class="py-1.5 text-right">{{ __('Outstanding') }}</th><th class="py-1.5 text-right">{{ __('Rate') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($c['by_category'] as $t)
                            <tr>
                                <td class="py-1.5 text-gray-800">{{ $t['name'] }}</td><td class="py-1.5 text-right tabular-nums">{{ $fmt($t['billed']) }}</td>
                                <td class="py-1.5 text-right tabular-nums text-emerald-700">{{ $fmt($t['collected']) }}</td><td class="py-1.5 text-right tabular-nums">{{ $fmt($t['outstanding']) }}</td>
                                <td class="py-1.5 text-right tabular-nums font-medium">{{ number_format($t['rate'], 0) }}%</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if($c['debtors'])
            <div class="mt-5 overflow-x-auto">
                <h5 class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">{{ __('The largest balances still owed') }}</h5>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @foreach($c['debtors'] as $d)
                        <tr>
                            <td class="py-1.5"><a href="{{ route('admin.students.show', $d['student_id']) }}" class="text-blue-600 hover:underline">{{ $d['name'] }}</a> <span class="text-xs text-gray-400">{{ $d['matricule'] }}</span></td>
                            <td class="py-1.5 text-gray-500">{{ $d['class'] }}</td>
                            <td class="py-1.5 text-right tabular-nums text-gray-500">{{ __('of :amount', ['amount' => $fmt($d['billed'])]) }}</td>
                            <td class="py-1.5 text-right tabular-nums font-semibold text-red-700">{{ $fmt($d['owed']) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
            {!! $note(__('Green paid in full, amber in part, red nothing at all. The rate is the money collected against the money billed, after discounts and waivers.'), __('A class far below the others is the one to follow up first; the balances list is where a phone call does the most.')) !!}
        @endif
    </div>
</div>
@endsection

@push('scripts')
@if($a['has_data'])
{{-- Served from the app: a school server may have no internet. --}}
<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
<script>
(function () {
    const months = @json(collect($a['months'])->pluck('short'));
    const money = (v) => Number(v).toLocaleString();
    const charts = {};
    const base = { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + money(c.parsed.y ?? c.parsed.x) } } } };

    charts.cash = new Chart(document.getElementById('chart-cash'), {
        type: 'line',
        data: { labels: months, datasets: [{ label: @json(__('Balance at month end')), data: @json(collect($a['months'])->pluck('balance')), borderColor: '#1f3a5f', backgroundColor: 'rgba(31,58,95,0.08)', fill: true, tension: 0.25, pointRadius: 3 }] },
        options: { ...base, scales: { y: { ticks: { callback: money }, grid: { color: (c) => c.tick.value === 0 ? '#dc2626' : '#eef2f7' } } } },
    });

    charts.monthly = new Chart(document.getElementById('chart-monthly'), {
        type: 'bar',
        data: { labels: months, datasets: [
            { label: @json(__('Money in')), data: @json(collect($a['months'])->pluck('in')), backgroundColor: '#10b981' },
            { label: @json(__('Money out')), data: @json(collect($a['months'])->pluck('out')), backgroundColor: '#f87171' },
        ] },
        options: { ...base, scales: { y: { ticks: { callback: money } } } },
    });

    @if($pacing && $pacing['status'] !== 'none')
    @php $pacingColour = ['good' => '#10b981', 'warning' => '#f59e0b', 'critical' => '#dc2626'][$pacing['status']] ?? '#94a3b8'; @endphp
    charts.pacing = new Chart(document.getElementById('chart-pacing'), {
        type: 'bar',
        data: { labels: [@json(__('Year gone')), @json(__('Budget spent'))], datasets: [{ label: '%', data: [{{ round($pacing['elapsed'], 1) }}, {{ round($pacing['consumed'], 1) }}], backgroundColor: ['#94a3b8', @json($pacingColour)] }] },
        options: { ...base, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { min: 0, suggestedMax: 100, ticks: { callback: (v) => v + '%' } } } },
    });
    @endif

    // The meeting pack carries the charts as the page drew them.
    document.getElementById('meeting-pack')?.addEventListener('submit', function () {
        for (const [key, chart] of Object.entries(charts)) {
            const field = this.querySelector('input[name="charts[' + key + ']"]');
            if (field) field.value = chart.toBase64Image('image/png', 1);
        }
    });
})();
</script>
@endif
@endpush
