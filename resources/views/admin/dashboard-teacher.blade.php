@extends('layouts.admin')
@section('title', __('My Classes'))
@section('breadcrumb', __('Dashboard'))

@section('content')
<div class="space-y-5">
    <div class="bg-gradient-to-r from-[#1e293b] to-[#334155] rounded-xl p-6 text-white">
        <h2 class="text-2xl font-bold">{{ __('Good :part, :name', ['part' => now()->hour < 12 ? __('morning') : (now()->hour < 17 ? __('afternoon') : __('evening')), 'name' => auth()->user()->first_name]) }}</h2>
        <p class="text-gray-300 mt-1">{{ $school?->school_name }} · {{ $currentSession?->name }}{{ $term ? ' · '.$term->name : '' }}</p>
        @if($todo > 0)
            <p class="text-sm text-amber-200 mt-2">{{ trans_choice('{1} :count thing is waiting for you below.|[2,*] :count things are waiting for you below.', $todo, ['count' => $todo]) }}</p>
        @else
            <p class="text-sm text-emerald-200 mt-2">{{ __('Nothing is waiting for you. Everything due is done.') }}</p>
        @endif
    </div>

    @if($classes->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center">
            <h3 class="font-semibold text-gray-800">{{ __('You have no class yet.') }}</h3>
            <p class="text-sm text-gray-500 mt-1">{{ __('The head teacher assigns classes and subjects. Once that is done, your classes appear here with their registers and marks.') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
        @foreach($classes as $c)
            @php $section = $c['section']; @endphp
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-gray-800">{{ $section->form->name }} — {{ $section->name }}</h3>
                        <p class="text-xs text-gray-500">{{ trans_choice('{0} no pupils|{1} :count pupil|[2,*] :count pupils', $c['pupils'], ['count' => $c['pupils']]) }}@if($c['class_teacher']) · <span class="text-teal-700 font-medium">{{ __('You are the class teacher') }}</span>@endif</p>
                    </div>
                </div>

                <div class="divide-y divide-gray-100">
                    @if($c['class_teacher'] && auth()->user()->can('attendance.mark'))
                        <a href="{{ route('admin.attendance.index', ['class_section_id' => $section->id]) }}" class="flex items-center justify-between px-5 py-3 hover:bg-gray-50">
                            <span class="text-sm text-gray-700">{{ __("Today's register") }}</span>
                            @if($c['register_taken'])
                                <span class="text-xs font-medium text-emerald-700">{{ __('Taken') }} · {{ __(':n absent', ['n' => $c['absent_today']]) }}</span>
                            @else
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">{{ __('Not taken yet') }}</span>
                            @endif
                        </a>
                    @endif

                    @if($c['nursery'])
                        <a href="{{ route('admin.nursery.index', ['class_section_id' => $section->id, 'term_id' => $term?->id]) }}" class="flex items-center justify-between px-5 py-3 hover:bg-gray-50">
                            <span class="text-sm text-gray-700">{{ __('Assessment sheets this term') }}</span>
                            <span class="text-xs font-medium {{ $c['sheets_done'] >= $c['pupils'] && $c['pupils'] > 0 ? 'text-emerald-700' : 'text-amber-700' }}">{{ $c['sheets_done'] }}/{{ $c['pupils'] }}</span>
                        </a>
                    @else
                        @forelse($c['marks'] as $row)
                            <div class="px-5 py-2.5 flex flex-col sm:flex-row sm:items-center gap-2">
                                <span class="text-sm text-gray-700 sm:w-44 shrink-0">{{ $row['subject']->name }}</span>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($row['sequences'] as $s)
                                        @php
                                            $tone = match ($s['status']) {
                                                'approved', 'published' => 'bg-emerald-100 text-emerald-800',
                                                'submitted' => 'bg-sky-100 text-sky-800',
                                                'returned' => 'bg-red-100 text-red-800',
                                                'draft' => 'bg-amber-100 text-amber-800',
                                                default => 'bg-gray-100 text-gray-600',
                                            };
                                            $label = match ($s['status']) {
                                                'approved' => __('Approved'), 'published' => __('Published'), 'submitted' => __('Submitted'),
                                                'returned' => __('Returned'), 'draft' => __(':n/:t entered', ['n' => $s['entered'], 't' => $c['pupils']]),
                                                default => __('Not started'),
                                            };
                                        @endphp
                                        <a href="{{ route('admin.marks.index', ['form_id' => $section->form_id, 'class_section_id' => $section->id, 'subject_id' => $row['subject']->id, 'term_id' => $term?->id, 'sequence_id' => $s['sequence']->id, 'education_system' => $section->form->education_system]) }}"
                                           class="text-xs px-2 py-1 rounded-md {{ $tone }} hover:ring-2 hover:ring-offset-1 hover:ring-gray-300" title="{{ $s['sequence']->name }}">
                                            {{ $s['sequence']->name }}: {{ $label }}
                                        </a>
                                    @endforeach
                                    @if(empty($row['sequences']))
                                        <span class="text-xs text-gray-400">{{ __('No sequence is set for this term.') }}</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="px-5 py-3 text-sm text-gray-400">{{ __('No subject of this class is assigned to you.') }}</p>
                        @endforelse
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if($notices->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-800 mb-3">{{ __('Notices for staff') }}</h3>
            <ul class="divide-y divide-gray-100">
                @foreach($notices as $notice)
                    <li class="py-2 text-sm"><span class="font-medium text-gray-800">{{ $notice->title }}</span> <span class="text-xs text-gray-400">· {{ optional($notice->published_at)->format('d M') }}</span></li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
