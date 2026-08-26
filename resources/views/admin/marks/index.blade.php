@extends('layouts.admin')

@section('title', __('Marks Entry'))
@section('breadcrumb', __('Examinations') . ' > ' . __('Marks Entry'))

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('Marks Entry') }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ __('Select class, subject & sequence, then enter student marks') }}</p>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
        <div class="flex items-center">
            <svg class="w-5 h-5 text-green-500 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-green-800 text-sm font-medium">{{ session('success') }}</span>
        </div>
    </div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="flex items-center">
            <svg class="w-5 h-5 text-red-500 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-red-800 text-sm font-medium">{{ session('error') }}</span>
        </div>
    </div>
    @endif
    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <ul class="list-disc list-inside text-sm text-red-700">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Filter Bar --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form method="GET" action="{{ route('admin.marks.index') }}" id="filterForm">
            {{-- Row 1: Session, Educational System, Form, Section --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                {{-- Session --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Session') }} <span class="text-red-500">*</span></label>
                    <select name="academic_session_id" id="sessionSelect" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white"
                            style="appearance: auto; -webkit-appearance: menulist;">
                        <option value="">{{ __('— Select Session —') }}</option>
                        @foreach($sessions as $s)
                        <option value="{{ $s->id }}" {{ $sessionId == $s->id ? 'selected' : '' }}>
                            {{ $s->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Educational System --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Educational System') }} <span class="text-red-500">*</span></label>
                    <select name="education_system" id="eduSystemSelect" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white"
                            style="appearance: auto; -webkit-appearance: menulist;">
                        <option value="english" {{ ($educationSystem ?? 'english') === 'english' ? 'selected' : '' }}>{{ __('English') }}</option>
                        <option value="french" {{ ($educationSystem ?? '') === 'french' ? 'selected' : '' }}>{{ __('French') }}</option>
                    </select>
                </div>

                {{-- Form --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Form') }} <span class="text-red-500">*</span></label>
                    <select name="form_id" id="formSelect" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white"
                            style="appearance: auto; -webkit-appearance: menulist;">
                        <option value="">{{ __('— Select Form —') }}</option>
                        @foreach($forms as $form)
                        <option value="{{ $form->id }}" data-system="{{ $form->education_system }}"
                                {{ $formId == $form->id ? 'selected' : '' }}>
                            {{ $form->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Section --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Section') }} <span class="text-red-500">*</span></label>
                    <select name="class_section_id" id="sectionSelect" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white"
                            style="appearance: auto; -webkit-appearance: menulist;">
                        <option value="">{{ __('— Select Section —') }}</option>
                        @foreach($classSections as $cs)
                        <option value="{{ $cs->id }}" {{ $classSectionId == $cs->id ? 'selected' : '' }}>
                            {{ $cs->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Row 2: Subject, Term, Sequence, Load Button --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Subject --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Subject') }} <span class="text-red-500">*</span></label>
                    <select name="subject_id" id="subjectSelect" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white"
                            style="appearance: auto; -webkit-appearance: menulist;">
                        <option value="">{{ __('— Select Subject —') }}</option>
                        @foreach($subjects as $subj)
                        <option value="{{ $subj->id }}" {{ $subjectId == $subj->id ? 'selected' : '' }}>
                            {{ $subj->name }} ({{ $subj->code }})
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Term --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Term') }} <span class="text-red-500">*</span></label>
                    <select name="term_id" id="termSelect" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white"
                            style="appearance: auto; -webkit-appearance: menulist;">
                        <option value="">{{ __('— Select Term —') }}</option>
                        @foreach($terms as $t)
                        <option value="{{ $t->id }}" {{ $termId == $t->id ? 'selected' : '' }}>
                            {{ $t->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Sequence --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Sequence') }} <span class="text-red-500">*</span></label>
                    <select name="sequence_id" id="sequenceSelect" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white"
                            style="appearance: auto; -webkit-appearance: menulist;">
                        <option value="">{{ __('— Select Sequence —') }}</option>
                        @foreach($sequences as $seq)
                        <option value="{{ $seq->id }}" {{ $sequenceId == $seq->id ? 'selected' : '' }}>
                            {{ $seq->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Load Button --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">&nbsp;</label>
                    <button type="submit" id="loadBtn"
                            class="w-full inline-flex items-center justify-center px-6 py-2.5 text-white rounded-lg font-medium text-sm transition"
                            style="background-color: #0ea5e9;"
                            onmouseover="this.style.backgroundColor='#0284c7'"
                            onmouseout="this.style.backgroundColor='#0ea5e9'">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        {{ __('Load') }}
                    </button>
                </div>
            </div>
        </form>
    </div>

    @if($filtered && $classSection && $subject && $sequence)
    {{-- Marks Entry Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

        {{-- Info Bar --}}
        <div class="px-6 py-4 border-b border-gray-200" style="background-color: #f8fafc;">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ $classSection->name }} — {{ $subject->name }} ({{ $subject->code }})</h2>
                    <p class="text-sm text-gray-500 mt-0.5">
                        {{ $sessions->firstWhere('id', $sessionId)->name ?? '' }} &bull;
                        {{ $sequence->name }} &bull;
                        {{ __('Coefficient') }}: <strong>{{ $coefficient }}</strong> &bull;
                        {{ $enrollments->count() }} {{ __('student(s)') }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    @if($submission)
                    @php
                        $statusStyles = [
                            'draft' => 'background-color: #f3f4f6; color: #374151;',
                            'submitted' => 'background-color: #dbeafe; color: #1d4ed8;',
                            'approved' => 'background-color: #dcfce7; color: #15803d;',
                            'returned' => 'background-color: #fee2e2; color: #b91c1c;',
                        ];
                    @endphp
                    <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full" style="{{ $statusStyles[$submission->status] ?? '' }}">
                        {{ __(ucfirst($submission->status)) }}
                    </span>
                    @endif
                </div>
            </div>

            @if($submission && $submission->status === 'returned' && $submission->admin_comment)
            <div class="mt-3 p-3 rounded-lg text-sm" style="background-color: #fee2e2; border: 1px solid #fecaca; color: #b91c1c;">
                <strong>{{ __('Returned:') }}</strong> {{ $submission->admin_comment }}
            </div>
            @endif
        </div>

        @if($enrollments->isEmpty())
        {{-- No students --}}
        <div class="p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <h3 class="text-lg font-medium text-gray-600 mb-1">{{ __('No students found') }}</h3>
            <p class="text-sm text-gray-400">{{ __('No active students in this section have this subject enrolled.') }}</p>
        </div>
        @else

        {{-- Marks Form --}}
        <form method="POST" action="{{ route('admin.marks.save') }}" id="marksForm">
            @csrf
            <input type="hidden" name="class_section_id" value="{{ $classSection->id }}">
            <input type="hidden" name="subject_id" value="{{ $subject->id }}">
            <input type="hidden" name="sequence_id" value="{{ $sequence->id }}">

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="background-color: #1e293b; color: #fff;">
                            <th class="px-4 py-3 text-left w-12 font-medium">{{ __('S/N') }}</th>
                            <th class="px-4 py-3 text-left font-medium">{{ __('Student ID') }}</th>
                            <th class="px-4 py-3 text-left font-medium">{{ __('Student Name') }}</th>
                            <th class="px-4 py-3 text-center w-36 font-medium">{{ __('Marks') }} (/20)</th>
                            <th class="px-4 py-3 text-center w-20 font-medium">{{ __('Absent') }}</th>
                            <th class="px-4 py-3 text-center w-20 font-medium">{{ __('Grade') }}</th>
                            <th class="px-4 py-3 text-center w-24 font-medium">{{ __('Note') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($enrollments as $i => $enrollment)
                        @php
                            $mark = $existingMarks[$enrollment->id] ?? null;
                            $isLocked = $submission && in_array($submission->status, ['submitted', 'approved']);
                            $score = $mark && !$mark->is_absent ? $mark->score : '';
                            $rowBg = $i % 2 === 0 ? '' : 'background-color: #f9fafb;';
                        @endphp
                        <tr style="{{ $rowBg }}" id="row-{{ $enrollment->id }}">
                            <td class="px-4 py-2.5 text-gray-400 font-medium">{{ $i + 1 }}</td>
                            <td class="px-4 py-2.5 text-gray-500 font-mono text-xs">{{ $enrollment->student->student_id }}</td>
                            <td class="px-4 py-2.5 font-medium text-gray-800">
                                {{ $enrollment->student->last_name }} {{ $enrollment->student->first_name }}
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <input type="hidden" name="marks[{{ $i }}][enrollment_id]" value="{{ $enrollment->id }}">
                                <input type="number"
                                       name="marks[{{ $i }}][score]"
                                       id="score-{{ $enrollment->id }}"
                                       value="{{ $score }}"
                                       min="0" max="20" step="0.5"
                                       class="w-24 px-2 py-1.5 border border-gray-300 rounded text-center text-sm focus:ring-2 focus:ring-blue-500 outline-none score-input"
                                       data-row="{{ $enrollment->id }}"
                                       placeholder="0 - 20"
                                       {{ $isLocked ? 'disabled' : '' }}
                                       {{ $mark && $mark->is_absent ? 'disabled' : '' }}>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <input type="checkbox"
                                       name="marks[{{ $i }}][is_absent]"
                                       value="1"
                                       id="absent-{{ $enrollment->id }}"
                                       class="w-4 h-4 border-gray-300 rounded absent-checkbox"
                                       style="accent-color: #dc2626;"
                                       data-row="{{ $enrollment->id }}"
                                       {{ $mark && $mark->is_absent ? 'checked' : '' }}
                                       {{ $isLocked ? 'disabled' : '' }}>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <span id="grade-{{ $enrollment->id }}"
                                      class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full"
                                      style="{{ $mark && $mark->is_absent ? 'background-color: #fee2e2; color: #b91c1c;' : ($mark && $mark->grade ? 'background-color: #dbeafe; color: #1d4ed8;' : 'color: #9ca3af;') }}">
                                    {{ $mark && $mark->is_absent ? 'ABS' : ($mark->grade ?? '—') }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-center" id="note-{{ $enrollment->id }}">
                                @if($mark && $mark->score !== null && !$mark->is_absent)
                                    @if($mark->score >= 10)
                                    <span class="text-xs font-medium" style="color: #15803d;">{{ __('Pass') }}</span>
                                    @else
                                    <span class="text-xs font-medium" style="color: #b91c1c;">{{ __('Fail') }}</span>
                                    @endif
                                @elseif($mark && $mark->is_absent)
                                    <span class="text-xs font-medium" style="color: #d97706;">{{ __('Absent') }}</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Stats Bar --}}
            @if($submission && $submission->marks_entered > 0)
            <div class="px-6 py-3 border-t border-gray-100 flex flex-wrap gap-6 text-sm" style="background-color: #f8fafc;">
                <div><span class="text-gray-500">{{ __('Entered:') }}</span> <strong>{{ $submission->marks_entered }}</strong> / {{ $submission->total_students }}</div>
                <div><span class="text-gray-500">{{ __('Class Average:') }}</span> <strong>{{ $submission->class_average !== null ? number_format($submission->class_average, 2) : '—' }}</strong></div>
                <div><span class="text-gray-500">{{ __('Highest:') }}</span> <strong style="color: #15803d;">{{ $submission->highest_mark !== null ? number_format($submission->highest_mark, 1) : '—' }}</strong></div>
                <div><span class="text-gray-500">{{ __('Lowest:') }}</span> <strong style="color: #b91c1c;">{{ $submission->lowest_mark !== null ? number_format($submission->lowest_mark, 1) : '—' }}</strong></div>
                <div><span class="text-gray-500">{{ __('Pass Rate:') }}</span> <strong>{{ $submission->pass_rate !== null ? number_format($submission->pass_rate, 1) . '%' : '—' }}</strong></div>
            </div>
            @endif

            {{-- Action Buttons --}}
            @if(!$submission || in_array($submission->status, ['draft', 'returned']))
            <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center gap-3">
                <button type="submit"
                        class="inline-flex items-center px-5 py-2 text-white rounded-lg text-sm font-medium transition"
                        style="background-color: #1e293b;"
                        onmouseover="this.style.backgroundColor='#334155'"
                        onmouseout="this.style.backgroundColor='#1e293b'">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ __('Save Marks') }}
                </button>
            </div>
            @endif
        </form>

        @if($submission && $submission->status === 'draft' && $submission->marks_entered > 0)
        <div class="px-6 pb-4 flex flex-wrap items-center gap-3">
            <form method="POST" action="{{ route('admin.marks.submit', $submission) }}" class="inline"
                  onsubmit="return confirm('{{ __('Submit these marks for approval? You will not be able to edit them until approved or returned.') }}')">
                @csrf
                <button type="submit"
                        class="inline-flex items-center px-5 py-2 text-white rounded-lg text-sm font-medium transition"
                        style="background-color: #2563eb;"
                        onmouseover="this.style.backgroundColor='#1d4ed8'"
                        onmouseout="this.style.backgroundColor='#2563eb'">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ __('Submit for Approval') }}
                </button>
            </form>
        </div>
        @endif

        @if($submission && $submission->status === 'submitted' && in_array(auth()->user()->role, ['super_admin', 'admin']))
        <div class="px-6 pb-4 flex flex-wrap items-center gap-3">
            <form method="POST" action="{{ route('admin.marks.approve', $submission) }}" class="inline"
                  onsubmit="return confirm('{{ __('Approve these marks? This action is final.') }}')">
                @csrf
                <button type="submit"
                        class="inline-flex items-center px-5 py-2 text-white rounded-lg text-sm font-medium transition"
                        style="background-color: #16a34a;"
                        onmouseover="this.style.backgroundColor='#15803d'"
                        onmouseout="this.style.backgroundColor='#16a34a'">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ __('Approve') }}
                </button>
            </form>
            <button type="button" onclick="document.getElementById('returnModal').classList.remove('hidden')"
                    class="inline-flex items-center px-5 py-2 text-white rounded-lg text-sm font-medium transition"
                    style="background-color: #dc2626;"
                    onmouseover="this.style.backgroundColor='#b91c1c'"
                    onmouseout="this.style.backgroundColor='#dc2626'">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                {{ __('Return for Correction') }}
            </button>
        </div>
        @endif

        @endif {{-- enrollments not empty --}}
    </div>

    {{-- Return Modal --}}
    @if($submission && $submission->status === 'submitted')
    <div id="returnModal" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background-color: rgba(0,0,0,0.4);">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
            <h4 class="text-lg font-semibold text-gray-800 mb-3">{{ __('Return Marks for Correction') }}</h4>
            <form method="POST" action="{{ route('admin.marks.return', $submission) }}">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Reason / Comment') }}</label>
                    <textarea name="admin_comment" rows="3" required
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 outline-none"
                              placeholder="{{ __('Explain why marks are being returned...') }}"></textarea>
                </div>
                <div class="mt-4 flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('returnModal').classList.add('hidden')"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('Cancel') }}</button>
                    <button type="submit"
                            class="px-5 py-2 text-white rounded-lg text-sm font-medium transition"
                            style="background-color: #dc2626;"
                            onmouseover="this.style.backgroundColor='#b91c1c'"
                            onmouseout="this.style.backgroundColor='#dc2626'">
                        {{ __('Return Marks') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    @else
    {{-- Not filtered yet --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
        <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
        <h3 class="text-lg font-medium text-gray-600 mb-1">{{ __('Select filters to begin') }}</h3>
        <p class="text-sm text-gray-400">{{ __('Choose session, form, section, subject, term and sequence above, then click Load to enter marks.') }}</p>
    </div>
    @endif

    {{-- Recent Submissions --}}
    @if($recentSubmissions->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-700">{{ __('Recent Submissions') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead style="background-color: #f9fafb;">
                    <tr class="text-gray-600 uppercase text-xs">
                        <th class="px-4 py-3 text-left">{{ __('Class') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Subject') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Sequence') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Teacher') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Entered') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Average') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Pass Rate') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($recentSubmissions as $sub)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">{{ $sub->classSection->name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $sub->subject->name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $sub->sequence->name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $sub->teacher ? trim(($sub->teacher->first_name ?? '') . ' ' . ($sub->teacher->last_name ?? '')) : '-' }}</td>
                        <td class="px-4 py-3 text-center">{{ $sub->marks_entered ?? 0 }} / {{ $sub->total_students ?? 0 }}</td>
                        <td class="px-4 py-3 text-center">{{ $sub->class_average !== null ? number_format($sub->class_average, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-center">{{ $sub->pass_rate !== null ? number_format($sub->pass_rate, 1) . '%' : '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $sColors = [
                                    'draft' => 'background-color: #f3f4f6; color: #374151;',
                                    'submitted' => 'background-color: #dbeafe; color: #1d4ed8;',
                                    'approved' => 'background-color: #dcfce7; color: #15803d;',
                                    'returned' => 'background-color: #fee2e2; color: #b91c1c;',
                                ];
                            @endphp
                            <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full" style="{{ $sColors[$sub->status] ?? '' }}">
                                {{ __(ucfirst($sub->status)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $subSection = $sub->classSection;
                                $subFormId = $subSection && $subSection->form ? $subSection->form->id : '';
                            @endphp
                            <a href="{{ route('admin.marks.index', [
                                'academic_session_id' => $sessionId,
                                'form_id' => $subFormId,
                                'class_section_id' => $sub->class_section_id,
                                'subject_id' => $sub->subject_id,
                                'sequence_id' => $sub->sequence_id,
                            ]) }}"
                               class="text-xs font-medium" style="color: #2563eb;">
                                {{ $sub->status === 'approved' ? __('View') : __('Edit') }}
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
    // ── Data ──
    const allForms = @json($forms->map(fn($f) => ['id' => $f->id, 'name' => $f->name, 'education_system' => $f->education_system]));
    const baseUrl = @json(url('admin/marks'));

    function escapeHtml(t) {
        const d = document.createElement('div');
        d.textContent = t;
        return d.innerHTML;
    }

    function clearSelect(id, placeholder) {
        document.getElementById(id).innerHTML = '<option value="">' + placeholder + '</option>';
    }

    // ── Educational System → Form ──
    function filterFormsBySystem() {
        const system = document.getElementById('eduSystemSelect').value;
        const formSelect = document.getElementById('formSelect');
        const currentVal = formSelect.value;
        formSelect.innerHTML = '<option value="">' + @json(__('— Select Form —')) + '</option>';
        allForms.filter(f => f.education_system === system).forEach(f => {
            const opt = document.createElement('option');
            opt.value = f.id;
            opt.textContent = f.name;
            if (f.id == currentVal) opt.selected = true;
            formSelect.appendChild(opt);
        });
    }

    document.getElementById('eduSystemSelect').addEventListener('change', function() {
        filterFormsBySystem();
        clearSelect('sectionSelect', @json(__('— Select Section —')));
        clearSelect('subjectSelect', @json(__('— Select Subject —')));
        clearSelect('termSelect', @json(__('— Select Term —')));
        clearSelect('sequenceSelect', @json(__('— Select Sequence —')));
    });

    // ── Form → Section + Subject + Term (AJAX) ──
    document.getElementById('formSelect').addEventListener('change', function() {
        const formId = this.value;
        clearSelect('sectionSelect', @json(__('— Select Section —')));
        clearSelect('subjectSelect', @json(__('— Select Subject —')));
        clearSelect('termSelect', @json(__('— Select Term —')));
        clearSelect('sequenceSelect', @json(__('— Select Sequence —')));

        if (!formId) return;

        fetch(baseUrl + '/sections-by-form/' + formId)
            .then(r => r.json())
            .then(data => {
                let html = '<option value="">' + @json(__('— Select Section —')) + '</option>';
                data.forEach(s => { html += '<option value="' + s.id + '">' + escapeHtml(s.name) + '</option>'; });
                document.getElementById('sectionSelect').innerHTML = html;
            });

        fetch(baseUrl + '/subjects-by-form/' + formId)
            .then(r => r.json())
            .then(data => {
                let html = '<option value="">' + @json(__('— Select Subject —')) + '</option>';
                data.forEach(s => { html += '<option value="' + s.id + '">' + escapeHtml(s.label) + '</option>'; });
                document.getElementById('subjectSelect').innerHTML = html;
            });

        fetch(baseUrl + '/terms-by-form/' + formId)
            .then(r => r.json())
            .then(data => {
                let html = '<option value="">' + @json(__('— Select Term —')) + '</option>';
                data.forEach(t => { html += '<option value="' + t.id + '">' + escapeHtml(t.name) + '</option>'; });
                document.getElementById('termSelect').innerHTML = html;
            });
    });

    // ── Term → Sequence (AJAX) ──
    document.getElementById('termSelect').addEventListener('change', function() {
        const termId = this.value;
        const formId = document.getElementById('formSelect').value;
        clearSelect('sequenceSelect', @json(__('— Select Sequence —')));

        if (!termId || !formId) return;

        fetch(baseUrl + '/sequences-by-form-term/' + formId + '/' + termId)
            .then(r => r.json())
            .then(data => {
                let html = '<option value="">' + @json(__('— Select Sequence —')) + '</option>';
                data.forEach(s => { html += '<option value="' + s.id + '">' + escapeHtml(s.name) + '</option>'; });
                document.getElementById('sequenceSelect').innerHTML = html;
            });
    });

    // ── Init ──
    filterFormsBySystem();

    // ── Grade lookup & Absent toggle ──
    @if($filtered && $gradeScales->isNotEmpty())
    const gradeScales = @json($gradeScales);

    function getGrade(score) {
        for (const gs of gradeScales) {
            if (score >= parseFloat(gs.min_mark) && score <= parseFloat(gs.max_mark)) return gs.grade;
        }
        return '—';
    }

    function updateNote(rowId, score, isAbsent) {
        const noteTd = document.getElementById('note-' + rowId);
        if (!noteTd) return;
        if (isAbsent) {
            noteTd.innerHTML = '<span class="text-xs font-medium" style="color: #d97706;">{{ __("Absent") }}</span>';
        } else if (score !== null && !isNaN(score)) {
            noteTd.innerHTML = score >= 10
                ? '<span class="text-xs font-medium" style="color: #15803d;">{{ __("Pass") }}</span>'
                : '<span class="text-xs font-medium" style="color: #b91c1c;">{{ __("Fail") }}</span>';
        } else {
            noteTd.innerHTML = '<span class="text-gray-300">—</span>';
        }
    }

    document.querySelectorAll('.score-input').forEach(input => {
        input.addEventListener('input', function() {
            const rowId = this.dataset.row;
            const gradeEl = document.getElementById('grade-' + rowId);
            const val = parseFloat(this.value);
            if (!isNaN(val) && val >= 0 && val <= 20) {
                gradeEl.textContent = getGrade(val);
                gradeEl.style.cssText = 'background-color: #dbeafe; color: #1d4ed8;';
                gradeEl.className = 'inline-flex px-2 py-0.5 text-xs font-semibold rounded-full';
                updateNote(rowId, val, false);
            } else {
                gradeEl.textContent = '—';
                gradeEl.style.cssText = 'color: #9ca3af;';
                gradeEl.className = 'inline-flex px-2 py-0.5 text-xs font-semibold rounded-full';
                updateNote(rowId, null, false);
            }
        });
    });

    document.querySelectorAll('.absent-checkbox').forEach(cb => {
        cb.addEventListener('change', function() {
            const rowId = this.dataset.row;
            const scoreInput = document.getElementById('score-' + rowId);
            const gradeEl = document.getElementById('grade-' + rowId);
            if (this.checked) {
                scoreInput.value = '';
                scoreInput.disabled = true;
                gradeEl.textContent = 'ABS';
                gradeEl.style.cssText = 'background-color: #fee2e2; color: #b91c1c;';
                gradeEl.className = 'inline-flex px-2 py-0.5 text-xs font-semibold rounded-full';
                updateNote(rowId, null, true);
            } else {
                scoreInput.disabled = false;
                gradeEl.textContent = '—';
                gradeEl.style.cssText = 'color: #9ca3af;';
                gradeEl.className = 'inline-flex px-2 py-0.5 text-xs font-semibold rounded-full';
                updateNote(rowId, null, false);
            }
        });
    });
    @endif
</script>
@endpush
@endsection
