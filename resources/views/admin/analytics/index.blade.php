@extends('layouts.admin')
@section('title', __('Academic Analytics'))
@section('breadcrumb', __('Academics > Analytics'))

@section('content')
<div class="space-y-5">

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Term') }}</label>
            <select name="term_id" style="appearance:auto;" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
                @foreach($terms as $t)
                    <option value="{{ $t->id }}" @selected($term?->id === $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Form') }}</label>
            <select name="form_id" style="appearance:auto;" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
                <option value="">{{ __('All forms') }}</option>
                @foreach($forms as $f)
                    <option value="{{ $f->id }}" @selected($formId === $f->id)>{{ $f->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Class') }}</label>
            <select name="class_section_id" style="appearance:auto;" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
                <option value="">{{ __('All classes') }}</option>
                @foreach($sections as $s)
                    <option value="{{ $s->id }}" @selected($classSectionId === $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="bg-[#1e293b] hover:bg-[#334155] text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Show') }}</button>
        @can('academic-analytics.export')
            <a href="{{ route('admin.analytics.export', request()->query()) }}" class="text-sm text-teal-600 hover:text-teal-700 px-2 py-2 font-medium">{{ __('Export subjects (CSV)') }}</a>
        @endcan
    </form>

    @if(! $term)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center">
            <p class="text-sm text-gray-500">{{ __('No terms have been set up, so there is nothing to analyse yet.') }}</p>
        </div>
    @elseif($report['headline']['students'] === 0)
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-6">
            <p class="text-sm text-amber-800">
                {{ __('No results have been published for :term yet. Figures appear here once report cards are generated and published.', ['term' => $term->name]) }}
            </p>
        </div>
    @else

        {{-- How much of the term this rests on --}}
        @if(($report['coverage']['percent'] ?? 100) < 100)
            <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
                <p class="text-sm text-amber-800">
                    {{ __('These figures cover :published of :enrolled enrolled pupils (:percent%). The rest have no published result for this term, so read the rates as partial.', [
                        'published' => $report['coverage']['published'],
                        'enrolled' => $report['coverage']['enrolled'],
                        'percent' => $report['coverage']['percent'] ?? 0,
                    ]) }}
                </p>
            </div>
        @endif

        {{-- Headline --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            @php($h = $report['headline'])
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <p class="text-xs text-gray-500">{{ __('Pupils') }}</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ $h['students'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <p class="text-xs text-gray-500">{{ __('Average') }}</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ $h['average'] !== null ? number_format($h['average'], 2) : '—' }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <p class="text-xs text-gray-500">{{ __('Pass rate') }}</p>
                <p class="text-2xl font-bold {{ ($h['pass_rate'] ?? 0) >= 50 ? 'text-emerald-600' : 'text-rose-600' }} mt-1">
                    {{ $h['pass_rate'] !== null ? $h['pass_rate'].'%' : '—' }}
                </p>
                <p class="text-[0.65rem] text-gray-400 mt-1">{{ __('Pass mark :mark', ['mark' => number_format($report['pass_mark'], 1)]) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <p class="text-xs text-gray-500">{{ __('Highest') }}</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ $h['highest'] !== null ? number_format($h['highest'], 2) : '—' }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <p class="text-xs text-gray-500">{{ __('Lowest') }}</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ $h['lowest'] !== null ? number_format($h['lowest'], 2) : '—' }}</p>
            </div>
        </div>

        {{-- Trends and distribution --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ __('Across the sequences') }}</h4>
                <canvas id="sequenceChart" height="150"></canvas>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ __('Across the terms') }}</h4>
                <canvas id="termChart" height="150"></canvas>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ __('Grade spread') }}</h4>
                <canvas id="bandChart" height="150"></canvas>
            </div>
        </div>

        {{-- Subjects --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h4 class="text-sm font-semibold text-gray-700 mb-1">{{ __('By subject') }}</h4>
            <p class="text-xs text-gray-500 mb-4">
                {{ __('Sorted by how a subject sits against what the same pupils average overall. A subject well below that is in trouble even when the class is strong.') }}
            </p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 border-b border-gray-200">
                            <th class="py-2 pr-3">{{ __('Subject') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('Entries') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('Average') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('Pass rate') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('Against pupil average') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('Highest') }}</th>
                            <th class="py-2 pl-3 text-right">{{ __('Lowest') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['subjects'] as $subject)
                            <tr class="border-b border-gray-50">
                                <td class="py-2 pr-3 font-medium text-gray-800">{{ $subject['name'] }}</td>
                                <td class="py-2 px-3 text-right text-gray-500">{{ $subject['entries'] }}</td>
                                <td class="py-2 px-3 text-right">{{ number_format($subject['average'], 2) }}</td>
                                <td class="py-2 px-3 text-right {{ ($subject['pass_rate'] ?? 0) >= 50 ? 'text-emerald-600' : 'text-rose-600' }}">
                                    {{ $subject['pass_rate'] !== null ? $subject['pass_rate'].'%' : '—' }}
                                </td>
                                <td class="py-2 px-3 text-right font-medium {{ $subject['delta'] < 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                    {{ $subject['delta'] > 0 ? '+' : '' }}{{ number_format($subject['delta'], 2) }}
                                </td>
                                <td class="py-2 px-3 text-right text-gray-500">{{ number_format($subject['highest'], 1) }}</td>
                                <td class="py-2 pl-3 text-right text-gray-500">{{ number_format($subject['lowest'], 1) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Classes and teachers --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h4 class="text-sm font-semibold text-gray-700 mb-4">{{ __('By class') }}</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 border-b border-gray-200">
                                <th class="py-2 pr-3">{{ __('Class') }}</th>
                                <th class="py-2 px-3 text-right">{{ __('Pupils') }}</th>
                                <th class="py-2 px-3 text-right">{{ __('Average') }}</th>
                                <th class="py-2 pl-3 text-right">{{ __('Pass rate') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['classes'] as $class)
                                <tr class="border-b border-gray-50">
                                    <td class="py-2 pr-3 font-medium text-gray-800">{{ $class['name'] }}</td>
                                    <td class="py-2 px-3 text-right text-gray-500">{{ $class['students'] }}</td>
                                    <td class="py-2 px-3 text-right">{{ $class['average'] !== null ? number_format($class['average'], 2) : '—' }}</td>
                                    <td class="py-2 pl-3 text-right {{ ($class['pass_rate'] ?? 0) >= 50 ? 'text-emerald-600' : 'text-rose-600' }}">
                                        {{ $class['pass_rate'] !== null ? $class['pass_rate'].'%' : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h4 class="text-sm font-semibold text-gray-700 mb-1">{{ __('By teacher') }}</h4>
                <p class="text-xs text-gray-500 mb-4">{{ __('From the teacher recorded on each result, which is who taught it that term.') }}</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 border-b border-gray-200">
                                <th class="py-2 pr-3">{{ __('Teacher') }}</th>
                                <th class="py-2 px-3 text-right">{{ __('Entries') }}</th>
                                <th class="py-2 px-3 text-right">{{ __('Average') }}</th>
                                <th class="py-2 pl-3 text-right">{{ __('Pass rate') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($report['teachers'] as $teacher)
                                <tr class="border-b border-gray-50">
                                    <td class="py-2 pr-3">
                                        <div class="font-medium text-gray-800">{{ $teacher['teacher'] }}</div>
                                        <div class="text-xs text-gray-400">{{ implode(', ', $teacher['subjects']) }}</div>
                                    </td>
                                    <td class="py-2 px-3 text-right text-gray-500">{{ $teacher['entries'] }}</td>
                                    <td class="py-2 px-3 text-right">{{ number_format($teacher['average'], 2) }}</td>
                                    <td class="py-2 pl-3 text-right {{ ($teacher['pass_rate'] ?? 0) >= 50 ? 'text-emerald-600' : 'text-rose-600' }}">
                                        {{ $teacher['pass_rate'] !== null ? $teacher['pass_rate'].'%' : '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-4 text-sm text-gray-500">{{ __('No teacher was recorded on these results.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- At risk --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h4 class="text-sm font-semibold text-gray-700 mb-1">{{ __('Pupils needing attention') }}</h4>
            <p class="text-xs text-gray-500 mb-4">
                {{ __('Below the pass mark, or down two marks or more on last term. A pupil can be falling and still passing, which no threshold list would show.') }}
            </p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 border-b border-gray-200">
                            <th class="py-2 pr-3">{{ __('Pupil') }}</th>
                            <th class="py-2 px-3">{{ __('Class') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('Average') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('Change') }}</th>
                            <th class="py-2 px-3 text-right">{{ __('Rank') }}</th>
                            <th class="py-2 pl-3">{{ __('Why') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report['at_risk'] as $pupil)
                            <tr class="border-b border-gray-50">
                                <td class="py-2 pr-3">
                                    <div class="font-medium text-gray-800">{{ $pupil['student'] }}</div>
                                    <div class="text-xs text-gray-400">{{ $pupil['student_id'] }}</div>
                                </td>
                                <td class="py-2 px-3 text-gray-600">{{ $pupil['class'] }}</td>
                                <td class="py-2 px-3 text-right">{{ number_format($pupil['average'], 2) }}</td>
                                <td class="py-2 px-3 text-right {{ ($pupil['change'] ?? 0) < 0 ? 'text-rose-600' : 'text-gray-400' }}">
                                    {{ $pupil['change'] !== null ? ($pupil['change'] > 0 ? '+' : '').number_format($pupil['change'], 2) : '—' }}
                                </td>
                                <td class="py-2 px-3 text-right text-gray-500">{{ $pupil['rank'] ?? '—' }}</td>
                                <td class="py-2 pl-3 text-xs text-gray-600">{{ $pupil['reason'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-4 text-sm text-emerald-600">{{ __('Nobody is below the pass mark or falling.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection

@if($term && $report && $report['headline']['students'] > 0)
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const sequenceData = @json($report['sequence_trend']);
const termData = @json($report['term_trend']);
const bandData = @json($report['bands']);

function line(id, rows, colour) {
    const el = document.getElementById(id);
    if (!el || !rows.length) return;
    new Chart(el, {
        type: 'line',
        data: {
            labels: rows.map(r => r.label),
            datasets: [{
                data: rows.map(r => r.average),
                borderColor: colour,
                backgroundColor: colour + '22',
                tension: 0.3,
                fill: true,
                // A term with no results is a gap in the line, not a zero.
                spanGaps: false,
            }],
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, suggestedMax: {{ (int) ($report['pass_mark'] * 2) }} } },
        },
    });
}

line('sequenceChart', sequenceData, '#0d9488');
line('termChart', termData, '#6366f1');

const bandEl = document.getElementById('bandChart');
if (bandEl && bandData.length) {
    new Chart(bandEl, {
        type: 'bar',
        data: {
            labels: bandData.map(b => b.grade),
            datasets: [{ data: bandData.map(b => b.count), backgroundColor: '#38bdf8' }],
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
        },
    });
}
</script>
@endpush
@endif
