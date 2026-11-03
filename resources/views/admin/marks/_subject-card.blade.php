{{--
    One of a teacher's subjects in one class, with this term's sequences.
    $card from App\Services\TeacherSubjects::cards(); $term may be null.
--}}
@php
    $section = $card['section'];
    $subject = $card['subject'];
    $next = app(\App\Services\TeacherSubjects::class)->nextUrl($card);
@endphp
<div class="w-full min-w-0 bg-white rounded-xl border border-gray-200 border-l-4 {{ $card['done'] ? 'border-l-emerald-500' : 'border-l-indigo-500' }} shadow-sm p-4 flex flex-col gap-3 transition hover:shadow-md hover:-translate-y-0.5">
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-1.5 mb-1">
                @if($subject?->code)<span class="px-2 py-0.5 rounded bg-gray-800 text-white text-[0.7rem] font-semibold">{{ $subject->code }}</span>@endif
                <span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 text-[0.7rem] font-semibold">{{ $section->name }}</span>
                @if($card['class_teacher'])<span class="px-2 py-0.5 rounded bg-teal-50 text-teal-700 text-[0.7rem]">{{ __('Class teacher') }}</span>@endif
            </div>
            <h4 class="font-semibold text-gray-800 truncate" title="{{ $subject?->name ?? __('Assessment sheets') }}">{{ $subject?->name ?? __('Assessment sheets') }}</h4>
        </div>
        <span class="shrink-0 px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-xs" title="{{ __('Pupils in the class') }}">{{ trans_choice('{0} no pupils|{1} :count pupil|[2,*] :count pupils', $card['pupils'], ['count' => $card['pupils']]) }}</span>
    </div>

    @if($card['nursery'])
        <div class="text-sm text-gray-600">
            {{ __('Competence sheets completed') }}:
            <strong class="{{ $card['done'] ? 'text-emerald-700' : 'text-amber-700' }}">{{ $card['sheets_done'] }}/{{ $card['pupils'] }}</strong>
        </div>
        <a href="{{ route('admin.nursery.index', ['class_section_id' => $section->id, 'term_id' => $term?->id]) }}" class="mt-auto text-center px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">{{ __('Fill the sheets') }}</a>
    @else
        <div class="flex flex-wrap gap-1.5">
            @forelse($card['sequences'] as $cell)
                @php [$label, $tone] = \App\Services\TeacherSubjects::badge($cell, $card['pupils']); @endphp
                <a href="{{ $cell['url'] }}" class="text-xs px-2 py-1 rounded-md {{ $tone }} hover:ring-2 hover:ring-offset-1 hover:ring-gray-300">
                    <span class="font-semibold">{{ $cell['sequence']->name }}</span> · {{ $label }}
                </a>
            @empty
                <span class="text-xs text-gray-400">{{ __('No sequence is set for this term.') }}</span>
            @endforelse
        </div>
        <div class="mt-auto flex gap-2">
            @if($next)
                <a href="{{ $next }}" class="flex-1 text-center px-4 py-2 rounded-lg {{ $card['done'] ? 'border border-gray-300 text-gray-700 hover:bg-gray-50' : 'bg-emerald-600 text-white hover:bg-emerald-700' }} text-sm font-medium">{{ $card['done'] ? __('View marks') : __('Enter marks') }}</a>
            @endif
            @can('student.view')
                <a href="{{ route('admin.class-list.index', ['class_section_id' => $section->id]) }}" class="px-3 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50" title="{{ __('Class List') }}">{{ __('Class list') }}</a>
            @endcan
        </div>
    @endif
</div>
