@extends('layouts.admin')
@section('title', __('Who Teaches What'))
@section('breadcrumb', __('Academics > Who Teaches What'))

@section('content')
@php $canEdit = auth()->user()->can('class-section.edit'); @endphp
<div class="space-y-5" x-data="{ open: {{ $open ?? 'null' }} }">

    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">{{ __('Who Teaches What') }}</h2>
            <p class="text-sm text-gray-500 mt-0.5 max-w-3xl">
                {{ __('The class teacher and a teacher for each subject, for :year. This decides what each teacher sees when they log in: their classes, their subjects, their registers. At nursery and primary the class teacher marks every subject of the class; at secondary each subject teacher marks their own.', ['year' => $session?->name ?? '—']) }}
            </p>
        </div>
        @if($gaps > 0)
            <span class="shrink-0 text-sm px-3 py-1.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-800">
                {{ trans_choice('{1} :count class still has work nobody is assigned to.|[2,*] :count classes still have work nobody is assigned to.', $gaps, ['count' => $gaps]) }}
            </span>
        @else
            <span class="shrink-0 text-sm px-3 py-1.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800">{{ __('Every class is covered.') }}</span>
        @endif
    </div>

    @if(! $session)
        <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-900">{{ __('No current academic year is set.') }}</div>
    @elseif($sections->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center text-sm text-gray-500">{{ __('No active class yet.') }}</div>
    @elseif(! $teachers->contains(fn ($t) => $t->isTeacher()))
        <div class="bg-sky-50 border border-sky-200 rounded-xl px-4 py-3 text-sm text-sky-900">
            {{ __('No teacher accounts exist yet. Add staff with the role Teacher under Human Resources, then come back here.') }}
        </div>
    @endif

    <div class="space-y-3">
        @foreach($sections as $section)
            @php
                $subjects = $subjectsBySection->get($section->id, collect());
                $mine = $assigned->get($section->id, collect());
                $primary = $section->form->school_level === 'nursery_primary';
                $covered = $primary ? (bool) $section->class_teacher_id : $subjects->every(fn ($s) => $mine->get($s->id));
                $teacherName = fn ($id) => optional($teachers->firstWhere('id', $id), fn ($t) => $t->first_name.' '.$t->last_name);
            @endphp
            <div class="bg-white rounded-xl shadow-sm border {{ $covered ? 'border-gray-200' : 'border-amber-300' }} overflow-hidden">
                <button type="button" @click="open = open === {{ $section->id }} ? null : {{ $section->id }}"
                        class="w-full px-5 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-left hover:bg-gray-50">
                    <div>
                        <span class="font-semibold text-gray-800">{{ $section->form->name }} — {{ $section->name }}</span>
                        <span class="block text-xs text-gray-500 mt-0.5">
                            {{ __('Class teacher') }}: <span class="{{ $section->class_teacher_id ? 'text-gray-700' : 'text-amber-700 font-medium' }}">{{ $section->classTeacher ? $section->classTeacher->first_name.' '.$section->classTeacher->last_name : __('not set') }}</span>
                            @unless($primary)
                                · {{ __(':done of :total subjects assigned', ['done' => $subjects->filter(fn ($s) => $mine->get($s->id))->count(), 'total' => $subjects->count()]) }}
                            @endunless
                        </span>
                    </div>
                    <span class="text-xs {{ $covered ? 'text-emerald-700' : 'text-amber-700' }} font-medium">{{ $covered ? __('Covered') : __('Needs a teacher') }}</span>
                </button>

                <form x-show="open === {{ $section->id }}" x-cloak method="POST" action="{{ route('admin.teaching-assignments.update', $section) }}" class="border-t border-gray-100 px-5 py-4 space-y-4">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-end">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Class teacher') }}</label>
                            <select name="class_teacher_id" {{ $canEdit ? '' : 'disabled' }} style="appearance:auto;" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white">
                                <option value="">{{ __('— None —') }}</option>
                                @foreach($teachers as $t)
                                    <option value="{{ $t->id }}" @selected($section->class_teacher_id === $t->id)>{{ $t->last_name }} {{ $t->first_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <p class="text-xs text-gray-500">
                            {{ $primary
                                ? __('Teaches every subject below unless you name someone else for one, and keeps the register and the report cards.')
                                : __('Keeps the register, sits on the class council and writes the class teacher\'s remark. Marks only the subjects you give them below.') }}
                        </p>
                    </div>

                    @if($subjects->isEmpty())
                        <p class="text-sm text-amber-700">{{ __('This class has no subjects yet. Give it its subjects first.') }}</p>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                            @foreach($subjects as $subject)
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ $subject->name }} <span class="text-gray-400">{{ $subject->code }}</span></label>
                                    <select name="subjects[{{ $subject->id }}]" {{ $canEdit ? '' : 'disabled' }} style="appearance:auto;" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white">
                                        <option value="">{{ $primary ? __('The class teacher') : __('— Not assigned —') }}</option>
                                        @foreach($teachers as $t)
                                            <option value="{{ $t->id }}" @selected((int) $mine->get($subject->id) === $t->id)>{{ $t->last_name }} {{ $t->first_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($canEdit)
                        <div class="flex justify-end">
                            <button type="submit" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-medium hover:bg-teal-700">{{ __('Save :class', ['class' => $section->name]) }}</button>
                        </div>
                    @endif
                </form>
            </div>
        @endforeach
    </div>
</div>
@endsection
