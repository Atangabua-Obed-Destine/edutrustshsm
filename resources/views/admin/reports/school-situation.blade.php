@extends('layouts.admin')
@section('title', __('State of the School'))
@section('breadcrumb', __('Academics > State of the School'))

@section('content')
<div class="space-y-5">

    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">{{ __('State of the School') }}</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ __('How the term went, in one place — results, attendance, money, and who joined or left.') }}
            </p>
        </div>

        <form method="GET" class="flex items-end gap-2">
            <select name="term_id" onchange="this.form.submit()" style="appearance:auto;"
                    class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white">
                @foreach($terms as $t)
                    <option value="{{ $t->id }}" @selected($term?->id === $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
            @if($term)
                <a href="{{ route('admin.analytics.school-report.pdf', ['term_id' => $term->id]) }}"
                   class="px-3 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50 transition whitespace-nowrap">
                    {{ __('Download (PDF)') }}
                </a>
            @endif
        </form>
    </div>

    @if(! $term)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center">
            <p class="text-sm text-gray-500">{{ __('No terms have been set up, so there is nothing to report on.') }}</p>
        </div>
    @else
        @php
            $enrolment = $report['enrolment'];
            $academics = $report['academics'];
            $attendance = $report['attendance'];
            $finance = $report['finance'];
            $councils = $report['councils'];
        @endphp

        {{-- ── What it comes down to ───────────────────────────────── --}}
        @if(count($report['highlights']))
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-1">{{ __('What this term comes down to') }}</h3>
                <p class="text-xs text-gray-500 mb-4">
                    {{ $report['previous_term']
                        ? __('Compared with :term.', ['term' => $report['previous_term']->name])
                        : __('No earlier term to compare with.') }}
                </p>

                <ul class="space-y-2">
                    @foreach($report['highlights'] as $highlight)
                        @php
                            $tone = match($highlight['tone']) {
                                'good' => ['bg-emerald-500', 'text-gray-700'],
                                'bad' => ['bg-rose-500', 'text-gray-700'],
                                'warning' => ['bg-amber-500', 'text-gray-600'],
                                default => ['bg-gray-300', 'text-gray-600'],
                            };
                        @endphp
                        <li class="flex items-start gap-3">
                            <span class="w-2 h-2 rounded-full mt-1.5 shrink-0 {{ $tone[0] }}"></span>
                            <span class="text-sm {{ $tone[1] }}">{{ $highlight['text'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ── Movement in and out ─────────────────────────────────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 lg:col-span-2">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ __('Who is here') }}</h3>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    @php
                        $cards = [
                            [__('On the roll'), $enrolment['active'], null],
                            [__('Admitted'), '+'.$enrolment['admitted'], 'text-emerald-600'],
                            [__('Left'), '−'.$enrolment['left'], $enrolment['left'] > 0 ? 'text-rose-600' : null],
                            [__('Net change'), ($enrolment['net'] >= 0 ? '+' : '').$enrolment['net'],
                                $enrolment['net'] >= 0 ? 'text-emerald-600' : 'text-rose-600'],
                        ];
                    @endphp
                    @foreach($cards as [$label, $value, $tone])
                        <div>
                            <p class="text-[0.65rem] uppercase tracking-wide text-gray-400">{{ $label }}</p>
                            <p class="text-xl font-bold {{ $tone ?? 'text-gray-800' }} mt-0.5">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>

                @if(count($enrolment['by_reason']))
                    <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-gray-100">
                        @foreach($enrolment['by_reason'] as $reason => $count)
                            <span class="px-2 py-1 rounded-full bg-gray-100 text-gray-600 text-xs">
                                {{ __(ucfirst($reason)) }} <strong>{{ $count }}</strong>
                            </span>
                        @endforeach
                    </div>
                @endif

                @if(count($enrolment['by_form']))
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-[0.65rem] uppercase tracking-wide text-gray-400 mb-2">{{ __('By form') }}</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($enrolment['by_form'] as $form => $count)
                                <span class="px-2 py-1 rounded-lg bg-gray-50 border border-gray-200 text-xs text-gray-700">
                                    {{ $form }} <strong>{{ $count }}</strong>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ __('Councils') }}</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <p class="text-[0.65rem] uppercase tracking-wide text-gray-400">{{ __('Held') }}</p>
                        <p class="text-xl font-bold text-gray-800 mt-0.5">{{ $councils['held'] }}</p>
                    </div>
                    <div>
                        <p class="text-[0.65rem] uppercase tracking-wide text-gray-400">{{ __('Concluded') }}</p>
                        <p class="text-xl font-bold text-gray-800 mt-0.5">{{ $councils['concluded'] }}</p>
                    </div>
                </div>
                @if(count($councils['awards']))
                    <div class="mt-4 pt-4 border-t border-gray-100 space-y-1.5">
                        @foreach($councils['awards'] as $award => $count)
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-600">{{ \App\Models\ClassCouncilEntry::awards()[$award] ?? $award }}</span>
                                <span class="font-semibold text-gray-800">{{ $count }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- ── The three measures ──────────────────────────────────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            @php
                $panels = [
                    [__('Results'), [
                        [__('Pupils with results'), $academics['students'], null],
                        [__('Average'), $academics['average'] !== null ? number_format($academics['average'], 2) : '—', null],
                        [__('Pass rate'), $academics['pass_rate'] !== null ? $academics['pass_rate'].'%' : '—', 'pass_rate'],
                        [__('Pupils needing attention'), $academics['at_risk'], null],
                    ]],
                    [__('Attendance'), [
                        [__('Rate'), $attendance['rate'] !== null ? $attendance['rate'].'%' : '—', 'attendance'],
                        [__('Days marked'), number_format($attendance['marked']), null],
                        [__('Absences'), number_format($attendance['absent']), null],
                    ]],
                    [__('Money'), [
                        [__('Fees billed'), number_format($finance['billed'], 0), null],
                        [__('Collected'), number_format($finance['collected'], 0), 'collection_rate'],
                        [__('Outstanding'), number_format($finance['outstanding'], 0), null],
                        [__('Surplus this term'), number_format($finance['surplus'], 0), 'surplus'],
                    ]],
                ];
            @endphp

            @foreach($panels as [$title, $rows])
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ $title }}</h3>
                    <div class="space-y-3">
                        @foreach($rows as [$label, $value, $compareKey])
                            @php($delta = $compareKey ? ($report['comparison'][$compareKey]['change'] ?? null) : null)
                            <div class="flex items-end justify-between gap-3">
                                <span class="text-xs text-gray-500">{{ $label }}</span>
                                <span class="text-right">
                                    <span class="text-sm font-semibold text-gray-800">{{ $value }}</span>
                                    @if($delta !== null && abs($delta) >= 0.1)
                                        <span class="block text-[0.65rem] {{ $delta > 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                            {{ $delta > 0 ? '▲' : '▼' }} {{ number_format(abs($delta), 1) }}
                                        </span>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ── Where the school is weakest ─────────────────────────── --}}
        @if(count($academics['struggling_subjects']))
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-700">{{ __('Subjects to look at') }}</h3>
                <p class="text-xs text-gray-500 mt-0.5 mb-4">
                    {{ __('Measured against what the same pupils average elsewhere, so a hard class is not mistaken for a failing subject.') }}
                </p>
                <div class="space-y-3">
                    @foreach($academics['struggling_subjects'] as $subject)
                        <div class="flex items-center gap-3">
                            <div class="w-40 shrink-0 text-sm font-medium text-gray-800 truncate">{{ $subject['name'] }}</div>
                            <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                @php($width = min(100, abs($subject['delta']) * 12))
                                <div class="h-full rounded-full {{ $subject['delta'] < 0 ? 'bg-rose-400' : 'bg-emerald-400' }}"
                                     style="width: {{ $width }}%"></div>
                            </div>
                            <div class="w-28 text-right text-xs">
                                <span class="font-semibold {{ $subject['delta'] < 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                    {{ $subject['delta'] > 0 ? '+' : '' }}{{ number_format($subject['delta'], 2) }}
                                </span>
                                <span class="text-gray-400">· {{ number_format($subject['average'], 1) }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ── Staff cost against income ───────────────────────────── --}}
        @if($finance['staff_share'] !== null)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('What the term cost') }}</h3>
                <div class="flex items-center gap-4 flex-wrap">
                    <div>
                        <p class="text-[0.65rem] uppercase tracking-wide text-gray-400">{{ __('Income') }}</p>
                        <p class="text-lg font-bold text-gray-800">{{ number_format($finance['income'], 0) }}</p>
                    </div>
                    <div>
                        <p class="text-[0.65rem] uppercase tracking-wide text-gray-400">{{ __('Spending') }}</p>
                        <p class="text-lg font-bold text-gray-800">{{ number_format($finance['expense'], 0) }}</p>
                    </div>
                    <div>
                        <p class="text-[0.65rem] uppercase tracking-wide text-gray-400">{{ __('Of which staff') }}</p>
                        <p class="text-lg font-bold text-gray-800">{{ number_format($finance['staff_cost'], 0) }}</p>
                    </div>
                    <div class="flex-1 min-w-48">
                        <div class="flex justify-between text-[0.65rem] text-gray-400 mb-1">
                            <span>{{ __('Staff share of income') }}</span>
                            <span>{{ $finance['staff_share'] }}%</span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full {{ $finance['staff_share'] > 70 ? 'bg-rose-400' : 'bg-teal-400' }}"
                                 style="width: {{ min(100, $finance['staff_share']) }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
