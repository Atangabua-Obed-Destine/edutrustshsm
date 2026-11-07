{{--
    One pupil's recorded year: the school's record of results. Shared by the
    staff screen and the parent portal. Expects the array from
    RecordedResults::recordFor().
--}}
@php
    $fmt = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
    $byTerm = $results->keyBy('term_id');
    $passMark = (float) ($school?->pass_mark ?: 10);
@endphp
<div class="record bg-white rounded-xl shadow-sm border border-gray-200 p-5 sm:p-8 text-gray-800">
    <div class="text-center mb-5">
        <div class="text-base font-bold uppercase">{{ $school?->school_name }}</div>
        <div class="text-xs text-gray-500">{{ $school?->city }}{{ $school?->phone ? ' — '.__('Tel').': '.$school->phone : '' }}</div>
        <div class="text-sm font-semibold mt-3 uppercase">{{ __('Record of results') }} — {{ $year->name }}</div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-sm mb-5">
        <div><span class="text-gray-500">{{ __('Pupil') }}:</span> <strong>{{ $student->full_name }}</strong></div>
        <div><span class="text-gray-500">{{ __('Matricule') }}:</span> <strong>{{ $student->student_id }}</strong></div>
        <div><span class="text-gray-500">{{ __('Class') }}:</span> <strong>{{ $section?->name }}</strong></div>
    </div>

    @if($results->isEmpty())
        <p class="text-sm text-gray-500 py-6 text-center">{{ __('No verified results for this year yet.') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-gray-50 text-xs uppercase text-gray-500">
                        <th class="border border-gray-200 px-2 py-1.5 text-left">{{ __('Subject') }}</th>
                        @foreach($results as $r)<th class="border border-gray-200 px-2 py-1.5 text-center">{{ $r->term?->name }}</th>@endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($subjects as $subject)
                        <tr>
                            <td class="border border-gray-200 px-2 py-1">{{ $subject->name }}</td>
                            @foreach($results as $r)
                                @php $m = $r->subjectResults->firstWhere('subject_id', $subject->id)?->term_average; @endphp
                                <td class="border border-gray-200 px-2 py-1 text-center tabular-nums {{ $m !== null && (float) $m < $passMark ? 'text-rose-600' : '' }}">{{ $fmt($m) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr class="font-semibold bg-gray-50">
                        <td class="border border-gray-200 px-2 py-1.5">{{ __('Term average') }}</td>
                        @foreach($results as $r)<td class="border border-gray-200 px-2 py-1.5 text-center tabular-nums">{{ $fmt($r->term_average) }}</td>@endforeach
                    </tr>
                    <tr>
                        <td class="border border-gray-200 px-2 py-1">{{ __('Rank') }}</td>
                        @foreach($results as $r)<td class="border border-gray-200 px-2 py-1 text-center">{{ $r->class_rank ? $r->class_rank.' / '.$r->total_students : '—' }}</td>@endforeach
                    </tr>
                    <tr>
                        <td class="border border-gray-200 px-2 py-1">{{ __('Class average') }}</td>
                        @foreach($results as $r)<td class="border border-gray-200 px-2 py-1 text-center tabular-nums">{{ $fmt($r->class_average) }}</td>@endforeach
                    </tr>
                    <tr>
                        <td class="border border-gray-200 px-2 py-1">{{ __('Conduct') }}</td>
                        @foreach($results as $r)<td class="border border-gray-200 px-2 py-1 text-center">{{ $r->conduct ? __(ucfirst(str_replace('_', ' ', $r->conduct))) : '—' }}</td>@endforeach
                    </tr>
                </tbody>
            </table>
        </div>

        @foreach($results->filter(fn ($r) => $r->class_teacher_remark) as $r)
            <p class="text-sm mt-2"><span class="text-gray-500">{{ $r->term?->name }}:</span> {{ $r->class_teacher_remark }}</p>
        @endforeach

        @if($showAnnual && ($enrollment->final_average !== null || in_array($enrollment->status, ['promoted', 'repeated'], true)))
            <div class="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm border-t border-gray-200 pt-4">
                <div><span class="text-gray-500">{{ __('Annual average') }}:</span> <strong>{{ $fmt($enrollment->final_average) }}</strong></div>
                <div><span class="text-gray-500">{{ __('Annual rank') }}:</span> <strong>{{ $enrollment->final_rank ?? '—' }}</strong></div>
                <div><span class="text-gray-500">{{ __('Decision') }}:</span> <strong>{{ match ($enrollment->status) { 'promoted' => __('Promoted'), 'repeated' => __('Repeats the class'), default => '—' } }}</strong></div>
            </div>
        @endif
    @endif

    <p class="mt-6 text-xs text-gray-500 border-t border-gray-100 pt-3">
        {{ __('Recorded from the school\'s :year mark sheets and report cards, before the school used this system.', ['year' => $year->name]) }}
        @if($verifiedBy){{ __('Verified against the paper by :name.', ['name' => $verifiedBy]) }}@endif
    </p>
</div>
