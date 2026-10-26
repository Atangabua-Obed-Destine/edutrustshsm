@extends('layouts.admin')
@section('title', __('Academic Record'))
@section('breadcrumb', __('Students > Academic Record'))

@php
    $summary = $history['summary'];
    $passMark = $history['pass_mark'];
@endphp

@section('content')
<div class="space-y-5">

    {{-- ── Who ─────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="flex items-start gap-4 min-w-0">
                @if($student->photo)
                    <img src="{{ asset('storage/'.$student->photo) }}" alt=""
                         class="w-14 h-14 rounded-xl object-cover border border-gray-200">
                @else
                    <div class="w-14 h-14 rounded-xl bg-gray-100 flex items-center justify-center text-gray-400 font-bold text-lg">
                        {{ strtoupper(substr($student->first_name, 0, 1).substr($student->last_name, 0, 1)) }}
                    </div>
                @endif
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-gray-800">{{ $student->first_name }} {{ $student->last_name }}</h2>
                    <p class="text-sm text-gray-500">{{ $student->student_id }}
                        @if($student->guardian) · {{ __('Guardian') }}: {{ $student->guardian->guardian_name }}@endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('admin.students.history.pdf', $student) }}"
                   class="px-3 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50 transition">{{ __('Download (PDF)') }}</a>
                <a href="{{ route('admin.students.show', $student) }}"
                   class="px-3 py-2 rounded-lg bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium transition">← {{ __('Back') }}</a>
            </div>
        </div>

        {{-- Headline --}}
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mt-5 pt-5 border-t border-gray-100">
            @php
                $cards = [
                    [__('Years here'), $summary['years'], null],
                    [__('Terms recorded'), $summary['terms'], null],
                    [__('Average'), $summary['average'] !== null ? number_format($summary['average'], 2) : '—',
                        $summary['average'] !== null ? ($summary['average'] >= $passMark ? 'text-emerald-600' : 'text-rose-600') : null],
                    [__('Best term'), $summary['best'] !== null ? number_format($summary['best'], 2) : '—', null],
                    [__('Weakest term'), $summary['worst'] !== null ? number_format($summary['worst'], 2) : '—', null],
                    [__('Attendance'), $summary['attendance_rate'] !== null ? $summary['attendance_rate'].'%' : '—', null],
                ];
            @endphp
            @foreach($cards as [$label, $value, $tone])
                <div>
                    <p class="text-[0.65rem] uppercase tracking-wide text-gray-400">{{ $label }}</p>
                    <p class="text-lg font-bold {{ $tone ?? 'text-gray-800' }} mt-0.5">{{ $value ?? '—' }}</p>
                </div>
            @endforeach
        </div>

        @if($summary['direction'] !== null)
            <p class="text-xs mt-4 {{ $summary['direction'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                {{ $summary['direction'] >= 0
                    ? __('Up :points points since their first recorded term.', ['points' => number_format(abs($summary['direction']), 2)])
                    : __('Down :points points since their first recorded term.', ['points' => number_format(abs($summary['direction']), 2)]) }}
            </p>
        @endif
    </div>

    @if(count($history['trend']) >= 2)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h4 class="text-sm font-semibold text-gray-700 mb-1">{{ __('Term by term') }}</h4>
            <p class="text-xs text-gray-500 mb-4">{{ __('The pupil against their class, so a dip in a hard term reads as what it is.') }}</p>
            <canvas id="historyChart" height="80"></canvas>
        </div>
    @endif

    {{-- ── Year by year ────────────────────────────────────────── --}}
    @forelse($history['years'] as $year)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">
                        {{ $year['session'] ?? __('Unknown session') }}
                        <span class="text-gray-400 font-normal">·</span>
                        {{ $year['class'] ?? '—' }}
                        @if($year['stream'])<span class="text-xs text-gray-500">({{ $year['stream'] }})</span>@endif
                    </h3>
                </div>
                <div class="flex items-center gap-3 text-xs">
                    @if($year['final_average'] !== null)
                        <span class="text-gray-600">{{ __('Year average') }}:
                            <strong class="{{ $year['final_average'] >= $passMark ? 'text-emerald-600' : 'text-rose-600' }}">{{ number_format($year['final_average'], 2) }}</strong>
                        </span>
                    @endif
                    @if($year['final_rank'])
                        <span class="text-gray-600">{{ __('Position') }}: <strong>{{ $year['final_rank'] }}</strong></span>
                    @endif
                    <span class="px-2 py-0.5 rounded-full {{ $year['status'] === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                        {{ __(ucfirst($year['status'] ?? 'unknown')) }}
                    </span>
                </div>
            </div>

            @if(! $year['has_results'])
                <p class="px-5 py-6 text-sm text-gray-500">{{ __('No published results for this year.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 border-b border-gray-200">
                                <th class="py-2 pl-5 pr-3">{{ __('Term') }}</th>
                                <th class="py-2 px-3 text-right">{{ __('Average') }}</th>
                                <th class="py-2 px-3 text-right">{{ __('Class average') }}</th>
                                <th class="py-2 px-3 text-right">{{ __('Position') }}</th>
                                <th class="py-2 px-3">{{ __('Conduct') }}</th>
                                <th class="py-2 px-3">{{ __('Attendance') }}</th>
                                <th class="py-2 pl-3 pr-5">{{ __('Council') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($year['terms'] as $term)
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="py-2.5 pl-5 pr-3">
                                        <span class="font-medium text-gray-800">{{ $term['term'] }}</span>
                                        @unless($term['published'])
                                            <span class="ml-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[0.6rem]">{{ __('Draft') }}</span>
                                        @endunless
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-semibold {{ $term['average'] !== null && $term['average'] < $passMark ? 'text-rose-600' : 'text-gray-800' }}">
                                        {{ $term['average'] !== null ? number_format($term['average'], 2) : '—' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right text-gray-500">
                                        {{ $term['class_average'] !== null ? number_format($term['class_average'], 2) : '—' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right text-gray-600">
                                        {{ $term['rank'] ? $term['rank'].($term['of'] ? '/'.$term['of'] : '') : '—' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-gray-600">
                                        {{ $term['conduct'] ? __(ucfirst(str_replace('_', ' ', $term['conduct']))) : '—' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-gray-600">
                                        @if($term['school_days'])
                                            {{ $term['present'] ?? 0 }}/{{ $term['school_days'] }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="py-2.5 pl-3 pr-5">
                                        @if($term['council'])
                                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $term['council']['classes'] }}">{{ $term['council']['label'] }}</span>
                                            @if($term['council']['remark'])
                                                <div class="text-xs text-gray-500 mt-1">{{ $term['council']['remark'] }}</div>
                                            @endif
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                </tr>
                                @if($term['teacher_remark'] || $term['principal_remark'])
                                    <tr class="border-b border-gray-50 last:border-0">
                                        <td colspan="7" class="pb-3 pl-5 pr-5 text-xs text-gray-500">
                                            @if($term['teacher_remark'])
                                                <span class="text-gray-400">{{ __('Class teacher') }}:</span> {{ $term['teacher_remark'] }}
                                            @endif
                                            @if($term['principal_remark'])
                                                <span class="text-gray-400 {{ $term['teacher_remark'] ? 'ml-3' : '' }}">{{ __('Principal') }}:</span> {{ $term['principal_remark'] }}
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @empty
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center">
            <p class="text-sm text-gray-500">{{ __('This pupil has no enrolment on record yet.') }}</p>
        </div>
    @endforelse

    {{-- ── Subjects over time ──────────────────────────────────── --}}
    @if(count($history['subjects']))
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h4 class="text-sm font-semibold text-gray-700">{{ __('Subjects over time') }}</h4>
            <p class="text-xs text-gray-500 mt-0.5 mb-4">{{ __('Weakest first — the list is for finding what needs work.') }}</p>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 border-b border-gray-200">
                            <th class="py-2 pr-3">{{ __('Subject') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('Terms') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('Average') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('Best') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('Worst') }}</th>
                            <th class="py-2 pl-3 text-right">{{ __('Since the first term') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($history['subjects'] as $subject)
                            <tr class="border-b border-gray-50">
                                <td class="py-2 pr-3 font-medium text-gray-800">{{ $subject['name'] }}</td>
                                <td class="py-2 px-3 text-right text-gray-500">{{ $subject['terms'] }}</td>
                                <td class="py-2 px-3 text-right font-semibold {{ $subject['average'] < $passMark ? 'text-rose-600' : 'text-gray-800' }}">
                                    {{ number_format($subject['average'], 2) }}
                                </td>
                                <td class="py-2 px-3 text-right text-gray-500">{{ number_format($subject['best'], 1) }}</td>
                                <td class="py-2 px-3 text-right text-gray-500">{{ number_format($subject['worst'], 1) }}</td>
                                <td class="py-2 pl-3 text-right">
                                    @if($subject['change'] === null)
                                        <span class="text-gray-300">—</span>
                                    @else
                                        <span class="{{ $subject['change'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                            {{ $subject['change'] > 0 ? '+' : '' }}{{ number_format($subject['change'], 2) }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection

@if(count($history['trend']) >= 2)
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const trend = @json($history['trend']);
const el = document.getElementById('historyChart');

if (el) {
    new Chart(el, {
        type: 'line',
        data: {
            labels: trend.map(t => t.label),
            datasets: [
                {
                    label: @json(__('This pupil')),
                    data: trend.map(t => t.average),
                    borderColor: '#0d9488',
                    backgroundColor: '#0d948822',
                    tension: 0.3,
                    fill: true,
                },
                {
                    label: @json(__('Class average')),
                    data: trend.map(t => t.class_average),
                    borderColor: '#cbd5e1',
                    borderDash: [5, 4],
                    tension: 0.3,
                    fill: false,
                    pointRadius: 2,
                },
            ],
        },
        options: {
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
            scales: { y: { beginAtZero: true, suggestedMax: {{ (int) ($history['pass_mark'] * 2) }} } },
        },
    });
}
</script>
@endpush
@endif
