@extends('layouts.admin')
@section('title', $session->exists ? __('Edit Exam Series') : __('New Exam Series'))

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ $session->exists ? __('Edit Exam Series') : __('New Exam Series') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('One GCE series: a level, a year, and the classes sitting it') }}</p>
    </div>

    @include('admin.gce._flash')

    <form method="POST" action="{{ $session->exists ? route('admin.gce.sessions.update', $session) : route('admin.gce.sessions.store') }}"
          class="bg-white rounded-xl border border-gray-100 p-6 space-y-6">
        @csrf
        @if($session->exists) @method('PUT') @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Series Name') }}</label>
                <input type="text" name="name" value="{{ old('name', $session->name) }}" required
                       placeholder="{{ __('June 2026 O-Level') }}"
                       class="w-full rounded-lg border-gray-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Level') }}</label>
                <select name="level" required class="w-full rounded-lg border-gray-200 text-sm">
                    <option value="o_level" @selected(old('level', $session->level) === 'o_level')>{{ __('Ordinary Level') }}</option>
                    <option value="a_level" @selected(old('level', $session->level) === 'a_level')>{{ __('Advanced Level') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Academic Year') }}</label>
                <select name="academic_session_id" required class="w-full rounded-lg border-gray-200 text-sm">
                    @foreach($academicSessions as $option)
                        <option value="{{ $option->id }}" @selected(old('academic_session_id', $session->academic_session_id) == $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Exam Year') }}</label>
                <input type="number" name="exam_year" value="{{ old('exam_year', $session->exam_year) }}" required min="2000" max="2100"
                       class="w-full rounded-lg border-gray-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Centre Number') }}</label>
                <input type="text" name="centre_number" value="{{ old('centre_number', $session->centre_number) }}"
                       class="w-full rounded-lg border-gray-200 text-sm">
                <p class="text-[11px] text-gray-400 mt-1">{{ __('Assigned by the Board. Candidate numbers are prefixed with it.') }}</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Status') }}</label>
                <select name="status" required class="w-full rounded-lg border-gray-200 text-sm">
                    @foreach(['draft', 'open', 'closed', 'submitted'] as $option)
                        <option value="{{ $option }}" @selected(old('status', $session->status ?? 'draft') === $option)>{{ __(ucfirst($option)) }}</option>
                    @endforeach
                </select>
                <p class="text-[11px] text-gray-400 mt-1">{{ __('Entries can only be added or changed while a series is open.') }}</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Registration Opens') }}</label>
                <input type="date" name="opens_on" value="{{ old('opens_on', $session->opens_on?->format('Y-m-d')) }}"
                       class="w-full rounded-lg border-gray-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Registration Closes') }}</label>
                <input type="date" name="closes_on" value="{{ old('closes_on', $session->closes_on?->format('Y-m-d')) }}"
                       class="w-full rounded-lg border-gray-200 text-sm">
            </div>
        </div>

        <div class="border-t border-gray-100 pt-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-1">{{ __('Subject Limits') }}</h2>
            <p class="text-xs text-gray-500 mb-3">{{ __('The Board changes these between series, so they are set here rather than fixed in the system.') }}</p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Minimum Subjects') }}</label>
                    <input type="number" name="min_subjects" value="{{ old('min_subjects', $session->min_subjects ?? 1) }}" required min="1" max="20"
                           class="w-full rounded-lg border-gray-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Maximum Subjects') }}</label>
                    <input type="number" name="max_subjects" value="{{ old('max_subjects', $session->max_subjects ?? 9) }}" required min="1" max="20"
                           class="w-full rounded-lg border-gray-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Base Fee') }}</label>
                    <input type="number" step="0.01" name="base_fee" value="{{ old('base_fee', $session->base_fee ?? 0) }}" required min="0"
                           class="w-full rounded-lg border-gray-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Fee per Subject') }}</label>
                    <input type="number" step="0.01" name="fee_per_subject" value="{{ old('fee_per_subject', $session->fee_per_subject ?? 0) }}" required min="0"
                           class="w-full rounded-lg border-gray-200 text-sm">
                </div>
            </div>
        </div>

        <div class="border-t border-gray-100 pt-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-1">{{ __('Classes Sitting This Series') }}</h2>
            <p class="text-xs text-gray-500 mb-3">{{ __('Without this the system cannot tell you who has not been entered yet.') }}</p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                @foreach($forms as $form)
                <label class="flex items-center gap-2 border border-gray-100 rounded-lg px-3 py-2 cursor-pointer hover:bg-gray-50">
                    <input type="checkbox" name="form_ids[]" value="{{ $form->id }}"
                           @checked(in_array($form->id, old('form_ids', $selectedForms)))
                           class="rounded text-indigo-600">
                    <span class="text-sm text-gray-700">{{ $form->name }}</span>
                </label>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end gap-2 border-t border-gray-100 pt-5">
            <a href="{{ route('admin.gce.sessions.index') }}" class="text-sm font-medium border border-gray-200 hover:bg-gray-50 rounded-lg px-4 py-2">{{ __('Cancel') }}</a>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg px-5 py-2">
                {{ $session->exists ? __('Save Changes') : __('Create Series') }}
            </button>
        </div>
    </form>

</div>
@endsection
