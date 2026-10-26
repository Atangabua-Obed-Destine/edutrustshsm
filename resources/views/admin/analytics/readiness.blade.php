@extends('layouts.admin')
@section('title', __('Exam Readiness'))
@section('breadcrumb', __('Academics > Exam Readiness'))

@section('content')
<div class="space-y-5">

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
        <button type="submit" class="bg-[#1e293b] hover:bg-[#334155] text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Check') }}</button>
    </form>

    @if(! $term)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center">
            <p class="text-sm text-gray-500">{{ __('No terms have been set up, so there is nothing to check.') }}</p>
        </div>
    @else
        @php
            $verdict = $report['verdict'];
            $banner = match($verdict) {
                'blocker' => ['bg-rose-50 border-rose-200 text-rose-800', __('Not ready to publish'), __('Publishing now would put wrong numbers on report cards. The blockers below have to be cleared first.')],
                'warning' => ['bg-amber-50 border-amber-200 text-amber-800', __('Ready, with loose ends'), __('The figures would be right. Something below is untidy rather than wrong.')],
                default => ['bg-emerald-50 border-emerald-200 text-emerald-800', __('Ready to publish'), __('Every expected mark is approved and every pupil has a result.')],
            };
        @endphp

        <div class="rounded-xl border p-5 {{ $banner[0] }}">
            <h3 class="text-base font-semibold">{{ $banner[1] }}</h3>
            <p class="text-sm mt-1">{{ $banner[2] }}</p>
            <p class="text-xs mt-2 opacity-80">
                {{ __(':classes class(es) · :expected expected subject-sequences · :pending outstanding', [
                    'classes' => $report['totals']['classes'],
                    'expected' => $report['totals']['expected'],
                    'pending' => $report['totals']['pending'],
                ]) }}
            </p>
        </div>

        {{-- The checks --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h4 class="text-sm font-semibold text-gray-700 mb-4">{{ __('Checks') }}</h4>
            <div class="space-y-2">
                @foreach($report['checks'] as $check)
                    @php
                        $dot = match($check['status']) {
                            'blocker' => 'bg-rose-500',
                            'warning' => 'bg-amber-500',
                            default => 'bg-emerald-500',
                        };
                    @endphp
                    <div class="flex items-start gap-3 py-2 border-b border-gray-50 last:border-0">
                        <span class="w-2 h-2 rounded-full mt-1.5 shrink-0 {{ $dot }}"></span>
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-gray-800">{{ $check['label'] }}</div>
                            <div class="text-xs text-gray-600">{{ $check['detail'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Class by class --}}
        @if(count($report['classes']))
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h4 class="text-sm font-semibold text-gray-700 mb-1">{{ __('Class by class') }}</h4>
                <p class="text-xs text-gray-500 mb-4">{{ __('Least complete first, so the chasing starts in the right place.') }}</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 border-b border-gray-200">
                                <th class="py-2 pr-3">{{ __('Class') }}</th>
                                <th class="py-2 px-3 text-right">{{ __('Approved') }}</th>
                                <th class="py-2 px-3 text-right">{{ __('Outstanding') }}</th>
                                <th class="py-2 pl-3">{{ __('Subjects outstanding') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['classes'] as $class)
                                <tr class="border-b border-gray-50">
                                    <td class="py-2 pr-3 font-medium text-gray-800">{{ $class['name'] }}</td>
                                    <td class="py-2 px-3 text-right">
                                        <span class="{{ ($class['percent'] ?? 0) == 100 ? 'text-emerald-600' : 'text-gray-700' }}">
                                            {{ $class['done'] }}/{{ $class['expected'] }}
                                            @if($class['percent'] !== null)
                                                <span class="text-xs text-gray-400">({{ $class['percent'] }}%)</span>
                                            @endif
                                        </span>
                                    </td>
                                    <td class="py-2 px-3 text-right {{ $class['pending'] > 0 ? 'text-rose-600 font-medium' : 'text-gray-400' }}">{{ $class['pending'] }}</td>
                                    <td class="py-2 pl-3 text-xs text-gray-600">{{ implode(', ', $class['subjects_outstanding']) ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Outstanding work --}}
        @if($report['pending']->isNotEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h4 class="text-sm font-semibold text-gray-700 mb-4">{{ __('What is outstanding') }}</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 border-b border-gray-200">
                                <th class="py-2 pr-3">{{ __('Subject') }}</th>
                                <th class="py-2 px-3">{{ __('Sequence') }}</th>
                                <th class="py-2 px-3 text-right">{{ __('Entered') }}</th>
                                <th class="py-2 pl-3">{{ __('State') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['pending'] as $unit)
                                <tr class="border-b border-gray-50">
                                    <td class="py-2 pr-3 font-medium text-gray-800">{{ $unit['subject'] }}</td>
                                    <td class="py-2 px-3 text-gray-600">{{ $unit['sequence'] }}</td>
                                    <td class="py-2 px-3 text-right text-gray-500">{{ $unit['entered'] }}/{{ $unit['total'] }}</td>
                                    <td class="py-2 pl-3">
                                        <span class="px-2 py-0.5 rounded-full text-xs {{ $unit['state'] === 'not_started' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700' }}">
                                            {{ $unit['label'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Pupils with nothing --}}
        @if($report['students_without_marks']->isNotEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h4 class="text-sm font-semibold text-gray-700 mb-1">{{ __('Pupils with no marks at all') }}</h4>
                <p class="text-xs text-gray-500 mb-4">{{ __('Usually a late admission or a subject registration that was never done. Each would be ranked on nothing.') }}</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 border-b border-gray-200">
                                <th class="py-2 pr-3">{{ __('Pupil') }}</th>
                                <th class="py-2 px-3">{{ __('Matricule') }}</th>
                                <th class="py-2 pl-3">{{ __('Class') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['students_without_marks'] as $pupil)
                                <tr class="border-b border-gray-50">
                                    <td class="py-2 pr-3 font-medium text-gray-800">{{ $pupil['student'] }}</td>
                                    <td class="py-2 px-3 text-gray-500">{{ $pupil['matricule'] }}</td>
                                    <td class="py-2 pl-3 text-gray-600">{{ $pupil['class'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
