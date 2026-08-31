@extends('layouts.admin')
@section('title', __('Enter Candidate'))

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    <div>
        <a href="{{ route('admin.gce.candidates.index', $session) }}" class="text-xs text-gray-400 hover:text-gray-600">&larr; {{ __('Candidates') }}</a>
        <h1 class="text-2xl font-bold text-gray-800 mt-1">{{ __('Enter Candidate') }}</h1>
        <p class="text-sm text-gray-500 mt-1">
            {{ $session->name }} · {{ $session->level_label }} ·
            {{ __('between :min and :max subjects', ['min' => $session->min_subjects, 'max' => $session->max_subjects]) }}
        </p>
    </div>

    @include('admin.gce._flash')

    @if($gceSubjects->isEmpty())
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
            <p class="text-sm text-amber-800">
                {{ __('No board subjects are set up for this level, so nobody can be entered yet.') }}
                <a href="{{ route('admin.gce.subjects.index', ['level' => $session->level]) }}" class="font-medium underline">{{ __('Add board subjects') }}</a>
            </p>
        </div>
    @elseif($eligible->isEmpty())
        <div class="bg-white rounded-xl border border-gray-100 p-10 text-center text-sm text-gray-400">
            @if($session->forms->isEmpty())
                {{ __('This series has no classes attached, so the system cannot tell who should sit it.') }}
            @else
                {{ __('Every eligible student has already been entered for this series.') }}
            @endif
        </div>
    @else
        <form method="GET" class="bg-white rounded-xl border border-gray-100 p-5">
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Filter by Class') }}</label>
            <div class="flex gap-2">
                <select name="class_section_id" class="rounded-lg border-gray-200 text-sm">
                    <option value="">{{ __('All classes') }}</option>
                    @foreach($classSections as $option)
                        <option value="{{ $option->id }}" @selected(request('class_section_id') == $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="text-sm font-medium border border-gray-200 hover:bg-gray-50 rounded-lg px-4 py-2">{{ __('Filter') }}</button>
            </div>
        </form>

        <form method="POST" action="{{ route('admin.gce.candidates.store', $session) }}"
              class="bg-white rounded-xl border border-gray-100 p-6 space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">{{ __('Student') }}</label>
                <select name="student_id" required class="w-full md:w-2/3 rounded-lg border-gray-200 text-sm">
                    <option value="">{{ __('Select a student...') }}</option>
                    @foreach($eligible as $enrollment)
                        <option value="{{ $enrollment->student_id }}" @selected(old('student_id') == $enrollment->student_id)>
                            {{ $enrollment->student?->last_name }} {{ $enrollment->student?->first_name }}
                            — {{ $enrollment->student?->student_id }} ({{ $enrollment->classSection?->name }})
                        </option>
                    @endforeach
                </select>
                <p class="text-[11px] text-gray-400 mt-1">
                    {{ trans_choice(':count student not yet entered|:count students not yet entered', $eligible->count(), ['count' => $eligible->count()]) }}
                </p>
            </div>

            <div class="border-t border-gray-100 pt-5">
                <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Subjects') }}</label>
                <p class="text-xs text-gray-500 mb-3">
                    {{ __('The Board rejects an entry outside the subject limits, so the count is checked before it is saved.') }}
                </p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                    @foreach($gceSubjects as $gceSubject)
                    <label class="flex items-center gap-2 border border-gray-100 rounded-lg px-3 py-2 cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" name="gce_subject_ids[]" value="{{ $gceSubject->id }}"
                               @checked(in_array($gceSubject->id, old('gce_subject_ids', [])))
                               class="rounded text-indigo-600">
                        <span class="text-sm text-gray-700">
                            <span class="font-mono text-xs text-gray-400">{{ $gceSubject->code }}</span>
                            {{ $gceSubject->name }}
                        </span>
                    </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-gray-100 pt-5">
                <a href="{{ route('admin.gce.candidates.index', $session) }}" class="text-sm font-medium border border-gray-200 hover:bg-gray-50 rounded-lg px-4 py-2">{{ __('Cancel') }}</a>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg px-5 py-2">{{ __('Enter Candidate') }}</button>
            </div>
        </form>
    @endif

</div>
@endsection
