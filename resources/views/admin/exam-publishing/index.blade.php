@extends('layouts.admin')

@section('title', __('Exam Publishing'))
@section('breadcrumb', __('Examinations') . ' > ' . __('Exam Publishing'))

@section('content')
<div class="space-y-6" style="max-width: 100%; overflow: hidden;">

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('Exam Publishing') }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ __('Review and publish exam marks by class, term and sequence') }}</p>
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

    {{-- Filter Bar --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form method="GET" action="{{ route('admin.exam-publishing.index') }}" id="filterForm">
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
                        <option value="{{ $s->id }}" {{ $sessionId == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
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
                                {{ $formId == $form->id ? 'selected' : '' }}>{{ $form->name }}</option>
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
                        <option value="{{ $cs->id }}" {{ $classSectionId == $cs->id ? 'selected' : '' }}>{{ $cs->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Row 2: Term, Sequence, Load Button --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Term --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Term') }} <span class="text-red-500">*</span></label>
                    <select name="term_id" id="termSelect" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white"
                            style="appearance: auto; -webkit-appearance: menulist;">
                        <option value="">{{ __('— Select Term —') }}</option>
                        @foreach($terms as $t)
                        <option value="{{ $t->id }}" {{ $termId == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
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
                        <option value="{{ $seq->id }}" {{ $sequenceId == $seq->id ? 'selected' : '' }}>{{ $seq->name }}</option>
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

    {{-- ══════════ RESULTS AREA ══════════ --}}
    @if($filtered && $classSection && $sequence && $stats)

    {{-- Statistics Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="rounded-xl p-5 text-center text-white" style="background-color: #0ea5e9;">
            <p class="text-3xl font-bold">{{ $stats->total_subjects }}</p>
            <p class="text-sm mt-1 opacity-90">{{ __('Total Subjects') }}</p>
        </div>
        <div class="rounded-xl p-5 text-center text-white" style="background-color: #06b6d4;">
            <p class="text-3xl font-bold">{{ $stats->total_students }}</p>
            <p class="text-sm mt-1 opacity-90">{{ __('Total Students') }}</p>
        </div>
        <div class="rounded-xl p-5 text-center text-white" style="background-color: #10b981;">
            <p class="text-3xl font-bold">{{ $stats->published_count }}/{{ $stats->total_subjects }}</p>
            <p class="text-sm mt-1 opacity-90">{{ __('Published Subjects') }}</p>
        </div>
        <div class="rounded-xl p-5 text-center text-white" style="background-color: #059669;">
            <p class="text-3xl font-bold">{{ $stats->with_marks_count }}/{{ $stats->total_subjects }}</p>
            <p class="text-sm mt-1 opacity-90">{{ __('With Marks') }}</p>
        </div>
    </div>

    {{-- Bulk Workflow Actions --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="border-l-4 border-blue-500 pl-4 pr-4 py-4 flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                <h2 class="text-lg font-semibold text-gray-800">{{ __('Bulk Workflow Actions') }}</h2>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="selectAllCheckboxes()"
                    style="background-color: white; border: 1px solid #d1d5db; color: #374151; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer;"
                    onmouseover="this.style.backgroundColor='#f3f4f6'" onmouseout="this.style.backgroundColor='white'">
                    ☑ {{ __('Select All') }}
                </button>
                <button type="button" onclick="deselectAllCheckboxes()"
                    style="background-color: white; border: 1px solid #d1d5db; color: #374151; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer;"
                    onmouseover="this.style.backgroundColor='#f3f4f6'" onmouseout="this.style.backgroundColor='white'">
                    ■ {{ __('Deselect All') }}
                </button>
                <button type="button" onclick="selectSameState()"
                    style="background-color: white; border: 1px solid #d1d5db; color: #374151; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer;"
                    onmouseover="this.style.backgroundColor='#f3f4f6'" onmouseout="this.style.backgroundColor='white'">
                    ▼ {{ __('Select Same State') }}
                </button>
            </div>
        </div>

        <div class="px-6 pb-5 pt-3">
            <form method="POST" action="{{ route('admin.exam-publishing.bulk-transition') }}" id="bulkForm">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">{{ __('Selected Subjects') }}</label>
                        <div id="selected_count_display" class="px-4 py-2.5 rounded-lg text-sm font-medium" style="background-color: #f1f5f9; color: #475569;">
                            0 {{ __('subject(s) selected') }}
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">{{ __('Transition To') }}</label>
                        <select name="transition_to" id="transitionSelect" required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white"
                                style="appearance: auto; -webkit-appearance: menulist;">
                            <option value="">{{ __('Select') }}</option>
                            <option value="submitted">{{ __('Submitted') }}</option>
                            <option value="approved">{{ __('Approved') }}</option>
                            <option value="published">{{ __('Published') }}</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" id="bulkTransitionBtn"
                                style="background-color: #0ea5e9; color: white; padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 6px; border: none; cursor: pointer; width: 100%; justify-content: center;"
                                onmouseover="this.style.backgroundColor='#0284c7'"
                                onmouseout="this.style.backgroundColor='#0ea5e9'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            {{ __('Apply Transition') }}
                        </button>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="block text-sm font-medium text-gray-500 mb-1">{{ __('Notes (Optional)') }}</label>
                    <input type="text" name="notes" placeholder="{{ __('Add notes for this transition...') }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm">
                </div>
                {{-- Hidden inputs for selected submission IDs --}}
                <div id="hidden_submission_ids"></div>
            </form>
        </div>
    </div>

    {{-- Workflow Progress Bar --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <div class="flex items-center justify-between text-sm mb-3">
            <span class="font-medium text-gray-700">{{ __('Publishing Progress') }}</span>
            <span class="text-gray-500">{{ $stats->published_count }}/{{ $stats->total_subjects }} {{ __('published') }}</span>
        </div>
        @php
            $draftCount = $subjectRows->whereIn('workflow_status', ['none', 'draft'])->count();
            $submittedCount = $stats->submitted_count;
            $approvedCount = $stats->approved_count;
            $publishedCount = $stats->published_count;
            $total = max($stats->total_subjects, 1);
        @endphp
        <div class="flex rounded-full h-5 overflow-hidden" style="background-color: #e2e8f0;">
            @if($publishedCount > 0)
            <div style="width: {{ ($publishedCount / $total) * 100 }}%; background-color: #10b981;" class="flex items-center justify-center text-white text-xs font-semibold">
                {{ $publishedCount }}
            </div>
            @endif
            @if($approvedCount > 0)
            <div style="width: {{ ($approvedCount / $total) * 100 }}%; background-color: #3b82f6;" class="flex items-center justify-center text-white text-xs font-semibold">
                {{ $approvedCount }}
            </div>
            @endif
            @if($submittedCount > 0)
            <div style="width: {{ ($submittedCount / $total) * 100 }}%; background-color: #f59e0b;" class="flex items-center justify-center text-white text-xs font-semibold">
                {{ $submittedCount }}
            </div>
            @endif
        </div>
        <div class="flex gap-5 mt-2 text-xs text-gray-500">
            <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-full" style="background-color: #10b981;"></span> {{ __('Published') }} ({{ $publishedCount }})</span>
            <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-full" style="background-color: #3b82f6;"></span> {{ __('Approved') }} ({{ $approvedCount }})</span>
            <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-full" style="background-color: #f59e0b;"></span> {{ __('Submitted') }} ({{ $submittedCount }})</span>
            <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-full" style="background-color: #e2e8f0;"></span> {{ __('Draft/None') }} ({{ $draftCount }})</span>
        </div>
    </div>

    {{-- Subjects Publishing Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="border-l-4 border-blue-500 pl-4 pr-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                <h2 class="text-lg font-semibold text-gray-800">{{ __('Subjects Publishing Status') }} ({{ $stats->total_subjects }} {{ __('Subjects') }})</h2>
            </div>
            <div class="text-sm text-gray-500">
                {{ $classSection->name }} &bull; {{ $sequence->name }}
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background-color: #334155; color: white;">
                        <th class="px-3 py-3 text-center w-10">
                            <input type="checkbox" id="masterCheckbox" onchange="toggleAllCheckboxes(this.checked)" class="rounded">
                        </th>
                        <th class="px-3 py-3 text-left font-semibold">{{ __('Code') }}</th>
                        <th class="px-3 py-3 text-left font-semibold">{{ __('Subject') }}</th>
                        <th class="px-3 py-3 text-center font-semibold">{{ __('CV') }}</th>
                        <th class="px-3 py-3 text-center font-semibold">{{ __('Students') }}</th>
                        <th class="px-3 py-3 text-center font-semibold">{{ __('With Marks') }}</th>
                        <th class="px-3 py-3 text-center font-semibold">{{ __('Pass') }}</th>
                        <th class="px-3 py-3 text-center font-semibold">{{ __('Fail') }}</th>
                        <th class="px-3 py-3 text-center font-semibold">{{ __('Avg') }}</th>
                        <th class="px-3 py-3 text-center font-semibold">{{ __('Workflow State') }}</th>
                        <th class="px-3 py-3 text-center font-semibold">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subjectRows as $i => $row)
                    <tr class="{{ $i % 2 === 0 ? '' : 'bg-gray-50' }} hover:bg-blue-50/30 transition-colors" data-status="{{ $row->workflow_status }}">
                        {{-- Checkbox --}}
                        <td class="px-3 py-3 text-center">
                            @if($row->submission_id)
                            <input type="checkbox" name="subject_check[]" value="{{ $row->submission_id }}" data-status="{{ $row->workflow_status }}" onchange="updateSelectedCount()" class="rounded subject-checkbox">
                            @endif
                        </td>

                        {{-- Code --}}
                        <td class="px-3 py-3">
                            <span style="background-color: #e2e8f0; color: #334155; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; font-family: monospace;">{{ $row->subject_code }}</span>
                        </td>

                        {{-- Subject Name --}}
                        <td class="px-3 py-3 font-medium text-gray-800">{{ $row->subject_name }}</td>

                        {{-- Coefficient --}}
                        <td class="px-3 py-3 text-center font-semibold">{{ number_format((float)$row->coefficient, 1) }}</td>

                        {{-- Students --}}
                        <td class="px-3 py-3 text-center">{{ $row->total_students }}</td>

                        {{-- With Marks --}}
                        <td class="px-3 py-3 text-center">
                            @if($row->with_marks > 0)
                            <span style="background-color: #dbeafe; color: #1e40af; padding: 2px 10px; border-radius: 6px; font-size: 12px; font-weight: 600;">{{ $row->with_marks }}</span>
                            @else
                            <span class="text-gray-400">0</span>
                            @endif
                        </td>

                        {{-- Pass --}}
                        <td class="px-3 py-3 text-center">{{ $row->pass_count }}</td>

                        {{-- Fail --}}
                        <td class="px-3 py-3 text-center">{{ $row->fail_count }}</td>

                        {{-- Average --}}
                        <td class="px-3 py-3 text-center font-medium">
                            @if($row->avg_score !== null)
                            {{ number_format($row->avg_score, 1) }}/20
                            @else
                            <span class="text-gray-400">—</span>
                            @endif
                        </td>

                        {{-- Workflow State --}}
                        <td class="px-3 py-3 text-center">
                            @php
                                $statusStyles = [
                                    'none' => 'background-color: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0;',
                                    'draft' => 'background-color: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0;',
                                    'submitted' => 'background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a;',
                                    'approved' => 'background-color: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe;',
                                    'published' => 'background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0;',
                                    'returned' => 'background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca;',
                                ];
                                $statusLabels = [
                                    'none' => __('No Marks'),
                                    'draft' => __('Draft'),
                                    'submitted' => __('Submitted'),
                                    'approved' => __('Approved'),
                                    'published' => __('Published'),
                                    'returned' => __('Returned'),
                                ];
                            @endphp
                            <span style="{{ $statusStyles[$row->workflow_status] ?? $statusStyles['none'] }} padding: 3px 12px; border-radius: 9999px; font-size: 11px; font-weight: 600; white-space: nowrap;">
                                {{ $statusLabels[$row->workflow_status] ?? ucfirst($row->workflow_status) }}
                            </span>
                        </td>

                        {{-- Actions --}}
                        <td class="px-3 py-3 text-center">
                            @if($row->submission_id)
                                @if($row->workflow_status === 'approved')
                                <form method="POST" action="{{ route('admin.exam-publishing.publish-subject') }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="submission_id" value="{{ $row->submission_id }}">
                                    <button type="submit" title="{{ __('Publish') }}"
                                        style="background-color: #d1fae5; color: #059669; border: 1px solid #a7f3d0; padding: 6px 10px; border-radius: 8px; cursor: pointer;"
                                        onmouseover="this.style.backgroundColor='#a7f3d0'" onmouseout="this.style.backgroundColor='#d1fae5'">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </button>
                                </form>
                                @elseif($row->workflow_status === 'published')
                                <form method="POST" action="{{ route('admin.exam-publishing.unpublish-subject') }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="submission_id" value="{{ $row->submission_id }}">
                                    <button type="submit" title="{{ __('Unpublish') }}"
                                        style="background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; padding: 6px 10px; border-radius: 8px; cursor: pointer;"
                                        onmouseover="this.style.backgroundColor='#fde68a'" onmouseout="this.style.backgroundColor='#fef3c7'">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    </button>
                                </form>
                                @else
                                <span class="text-gray-300">
                                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                </span>
                                @endif
                            @else
                            <span class="text-gray-300">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="11" class="px-4 py-8 text-center text-gray-400">
                            {{ __('No subjects configured for this form.') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ══════════ STUDENTS RESULTS PREVIEW ══════════ --}}
    @if(count($previewStudents) > 0)
    @php
        $previewSubjects = $formSubjects;
        $seqs = $termSequences;
        $seqCount = $seqs->count();
        // Columns per subject: one per sequence + AVG + Grd = seqCount + 2
        $colsPerSubject = $seqCount + 2;
        $totalCols = 3 + ($previewSubjects->count() * $colsPerSubject) + 3; // S/N, Mat, Name + subjects + Total, Avg, Rank

        // Grade color helper
        $gradeColor = function($grade) {
            return match($grade) {
                'A+' => ['bg' => '#059669', 'text' => '#ffffff'],
                'A'  => ['bg' => '#10b981', 'text' => '#ffffff'],
                'B+' => ['bg' => '#34d399', 'text' => '#064e3b'],
                'B'  => ['bg' => '#6ee7b7', 'text' => '#064e3b'],
                'C+' => ['bg' => '#06b6d4', 'text' => '#ffffff'],
                'C'  => ['bg' => '#67e8f9', 'text' => '#155e75'],
                'D+' => ['bg' => '#f59e0b', 'text' => '#ffffff'],
                'D'  => ['bg' => '#fbbf24', 'text' => '#78350f'],
                'E'  => ['bg' => '#ef4444', 'text' => '#ffffff'],
                'F'  => ['bg' => '#dc2626', 'text' => '#ffffff'],
                default => ['bg' => '#e2e8f0', 'text' => '#64748b'],
            };
        };

        // Score cell color
        $scoreColor = function($score) {
            if ($score === null) return '';
            if ($score >= 10) return 'color: #059669; font-weight: 600;';
            return 'color: #dc2626; font-weight: 600;';
        };
    @endphp

    <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; overflow: hidden; max-width: 100%;">
        {{-- Header bar with badge + actions --}}
        <div style="background-color: #f59e0b; padding: 12px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <svg style="width: 20px; height: 20px; color: #fff;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <h2 style="font-size: 1.1rem; font-weight: 700; color: #fff;">{{ __('Students Results Preview') }}</h2>
                <span style="background-color: #ffffff; color: #f59e0b; padding: 2px 10px; border-radius: 4px; font-size: 11px; font-weight: 700;">{{ __('DRAFT') }}</span>
            </div>
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <button type="button" onclick="togglePreviewView()"
                    style="background-color: #ffffff; color: #374151; padding: 7px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;"
                    onmouseover="this.style.backgroundColor='#f3f4f6'" onmouseout="this.style.backgroundColor='#ffffff'">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    {{ __('Toggle View') }}
                </button>
                <button type="button" onclick="window.print()"
                    style="background-color: #0ea5e9; color: #ffffff; padding: 7px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;"
                    onmouseover="this.style.backgroundColor='#0284c7'" onmouseout="this.style.backgroundColor='#0ea5e9'">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    {{ __('Print') }}
                </button>
                <span style="background-color: rgba(255,255,255,0.2); color: white; padding: 7px 14px; border-radius: 6px; font-size: 13px; font-weight: 600;">
                    {{ count($previewStudents) }} {{ __('Students') }} × {{ $previewSubjects->count() }} {{ __('Subjects') }}
                    &nbsp;|&nbsp;
                    <span style="color: #d1fae5;">{{ __('Pass') }}: {{ $previewPassCount }}</span>
                    &nbsp;|&nbsp;
                    <span style="color: #fecaca;">{{ __('Fail') }}: {{ $previewFailCount }}</span>
                </span>
            </div>
        </div>

        {{-- Collapsible preview body --}}
        <div id="previewBody" style="display: none;">
        {{-- Draft warning --}}
        <div style="background-color: #fefce8; border-bottom: 1px solid #fde68a; padding: 10px 20px; text-align: center;">
            <span style="color: #92400e; font-size: 13px;">
                ⚠ <strong>{{ __('DRAFT PREVIEW') }}</strong> — {{ __('This shows all results regardless of publishing status. Marks may change before final publication.') }}
            </span>
        </div>

        {{-- Scrollable table (both directions, sticky header + frozen left columns) --}}
        <div id="previewTableContainer" style="overflow: auto; max-height: 75vh; position: relative; width: 100%;">
            <table style="border-collapse: separate; border-spacing: 0; font-size: 0.75rem;" id="previewTable">
                {{-- Header Row 1: Subject groups --}}
                <thead>
                    <tr>
                        <th rowspan="3" style="background-color: #1e293b; color: white; padding: 8px 6px; position: sticky; left: 0; top: 0; z-index: 30; min-width: 40px; text-align: center; border-right: 1px solid #334155;">{{ __('S/N') }}</th>
                        <th rowspan="3" style="background-color: #1e293b; color: white; padding: 8px 6px; position: sticky; left: 40px; top: 0; z-index: 30; min-width: 110px; text-align: left; border-right: 1px solid #334155;">{{ __('Student ID') }}</th>
                        <th rowspan="3" style="background-color: #1e293b; color: white; padding: 8px 6px; position: sticky; left: 150px; top: 0; z-index: 30; min-width: 170px; text-align: left; border-right: 2px solid #475569;">{{ __('Name') }}</th>
                        @foreach($previewSubjects as $subj)
                        <th colspan="{{ $colsPerSubject }}" style="background-color: #0ea5e9; color: white; padding: 6px 4px; text-align: center; border-left: 2px solid #0284c7; font-size: 10px; font-weight: 700; white-space: nowrap; position: sticky; top: 0; z-index: 15;">
                            <span style="background-color: rgba(0,0,0,0.15); padding: 1px 6px; border-radius: 3px; font-family: monospace;">{{ $subj->subject_code }}</span>
                        </th>
                        @endforeach
                        {{-- Overall columns --}}
                        <th rowspan="3" style="background-color: #334155; color: #fbbf24; padding: 8px 6px; text-align: center; border-left: 3px solid #1e293b; min-width: 55px; font-weight: 700; position: sticky; top: 0; z-index: 15;">{{ __('TOT') }}</th>
                        <th rowspan="3" style="background-color: #334155; color: #fbbf24; padding: 8px 6px; text-align: center; min-width: 55px; font-weight: 700; position: sticky; top: 0; z-index: 15;">{{ __('AVG') }}</th>
                        <th rowspan="3" style="background-color: #334155; color: #fbbf24; padding: 8px 6px; text-align: center; min-width: 40px; font-weight: 700; position: sticky; top: 0; z-index: 15;">{{ __('Grd') }}</th>
                        <th rowspan="3" style="background-color: #334155; color: #fbbf24; padding: 8px 6px; text-align: center; min-width: 45px; font-weight: 700; position: sticky; top: 0; z-index: 15;">{{ __('Rank') }}</th>
                    </tr>
                    {{-- Header Row 2: Subject name + CV --}}
                    <tr>
                        @foreach($previewSubjects as $subj)
                        <td colspan="{{ $colsPerSubject }}" style="background-color: #0284c7; color: white; padding: 3px 4px; text-align: center; border-left: 2px solid #0369a1; font-size: 9px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: {{ $colsPerSubject * 45 }}px; position: sticky; top: 30px; z-index: 15;" title="{{ $subj->subject_name }}">
                            {{ Str::limit($subj->subject_name, 20) }}<br>
                            <strong>CV: {{ number_format((float)$subj->coefficient, 0) }}</strong>
                        </td>
                        @endforeach
                    </tr>
                    {{-- Header Row 3: Seq1, Seq2, AVG, Grd --}}
                    <tr>
                        @foreach($previewSubjects as $subj)
                            @foreach($seqs as $sIdx => $seq)
                            <th data-seq-col style="background-color: #475569; color: #e2e8f0; padding: 5px 3px; text-align: center; {{ $sIdx === 0 ? 'border-left: 2px solid #334155;' : '' }} font-size: 9px; min-width: 38px; font-weight: 600; position: sticky; top: 55px; z-index: 15;">
                                {{ __('S') }}{{ $seq->sequence_number }}
                            </th>
                            @endforeach
                            <th style="background-color: #334155; color: #fbbf24; padding: 5px 3px; text-align: center; font-size: 9px; min-width: 42px; font-weight: 700; position: sticky; top: 55px; z-index: 15;">{{ __('AVG') }}</th>
                            <th style="background-color: #334155; color: #fbbf24; padding: 5px 3px; text-align: center; font-size: 9px; min-width: 36px; font-weight: 700; position: sticky; top: 55px; z-index: 15;">{{ __('Grd') }}</th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @foreach($previewStudents as $student)
                    <tr style="border-bottom: 1px solid #e2e8f0; {{ $loop->even ? 'background-color: #f8fafc;' : '' }}"
                        data-row-even="{{ $loop->even ? '1' : '0' }}"
                        onmouseover="this.style.backgroundColor='#eff6ff'; this.querySelectorAll('[data-sticky-cell]').forEach(c => c.style.backgroundColor='#eff6ff')"
                        onmouseout="var bg = this.dataset.rowEven === '1' ? '#f8fafc' : '#ffffff'; this.style.backgroundColor = this.dataset.rowEven === '1' ? '#f8fafc' : ''; this.querySelectorAll('[data-sticky-cell]').forEach(c => c.style.backgroundColor=bg)">
                        {{-- S/N --}}
                        <td data-sticky-cell style="padding: 6px 5px; text-align: center; font-weight: 600; color: #64748b; position: sticky; left: 0; z-index: 10; border-right: 1px solid #e2e8f0; background-color: {{ $loop->even ? '#f8fafc' : '#ffffff' }};">{{ $student->sn }}</td>
                        {{-- Matricule --}}
                        <td data-sticky-cell style="padding: 6px 5px; font-family: monospace; font-size: 10px; color: #334155; position: sticky; left: 40px; z-index: 10; border-right: 1px solid #e2e8f0; white-space: nowrap; background-color: {{ $loop->even ? '#f8fafc' : '#ffffff' }};">{{ $student->matricule }}</td>
                        {{-- Name --}}
                        <td data-sticky-cell style="padding: 6px 5px; font-weight: 600; color: #1e293b; position: sticky; left: 150px; z-index: 10; border-right: 2px solid #cbd5e1; white-space: nowrap; background-color: {{ $loop->even ? '#f8fafc' : '#ffffff' }};">{{ $student->name }}</td>

                        @foreach($previewSubjects as $subj)
                            @php $sd = $student->subjects[$subj->subject_id] ?? null; @endphp
                            @if($sd && $sd['is_registered'])
                                {{-- Sequence scores --}}
                                @foreach($seqs as $sIdx => $seq)
                                    @php
                                        $seqData = $sd['seq_scores'][$seq->id] ?? null;
                                        $score = $seqData['score'] ?? null;
                                        $absent = $seqData['is_absent'] ?? false;
                                    @endphp
                                    <td data-seq-col style="padding: 5px 3px; text-align: center; {{ $sIdx === 0 ? 'border-left: 2px solid #e2e8f0;' : '' }} {{ $scoreColor($score) }}">
                                        @if($absent)
                                            <span style="background-color: #1e293b; color: white; padding: 1px 4px; border-radius: 3px; font-size: 9px; font-weight: 700;">ABS</span>
                                        @elseif($score !== null)
                                            {{ number_format($score, 1) }}
                                        @else
                                            <span style="color: #cbd5e1;">-</span>
                                        @endif
                                    </td>
                                @endforeach
                                {{-- Subject AVG --}}
                                <td style="padding: 5px 3px; text-align: center; font-weight: 700; {{ $scoreColor($sd['term_avg']) }}">
                                    {{ $sd['term_avg'] !== null ? number_format($sd['term_avg'], 1) : '-' }}
                                </td>
                                {{-- Subject Grade --}}
                                @php $gc = $gradeColor($sd['grade']); @endphp
                                <td style="padding: 5px 2px; text-align: center;">
                                    <span style="background-color: {{ $gc['bg'] }}; color: {{ $gc['text'] }}; padding: 2px 5px; border-radius: 4px; font-size: 9px; font-weight: 700; display: inline-block; min-width: 24px;" title="{{ $sd['grade_desc'] }}">{{ $sd['grade'] }}</span>
                                </td>
                            @else
                                {{-- Not registered for this subject --}}
                                @for($c = 0; $c < $colsPerSubject; $c++)
                                <td style="padding: 5px 3px; text-align: center; background-color: #f1f5f9; {{ $c === 0 ? 'border-left: 2px solid #e2e8f0;' : '' }}">
                                    <span style="color: #cbd5e1;">-</span>
                                </td>
                                @endfor
                            @endif
                        @endforeach

                        {{-- Overall Total (Weighted) --}}
                        <td style="padding: 5px 4px; text-align: center; font-weight: 700; color: #1e293b; border-left: 3px solid #e2e8f0; background-color: #fefce8;">
                            {{ $student->overall_avg !== null ? number_format($student->overall_weighted, 1) : '-' }}
                        </td>
                        {{-- Overall Average --}}
                        <td style="padding: 5px 4px; text-align: center; font-weight: 700; background-color: #fefce8; {{ $scoreColor($student->overall_avg) }}">
                            {{ $student->overall_avg !== null ? number_format($student->overall_avg, 2) : '-' }}
                        </td>
                        {{-- Overall Grade --}}
                        @php $ogc = $gradeColor($student->overall_grade); @endphp
                        <td style="padding: 5px 3px; text-align: center; background-color: #fefce8;">
                            <span style="background-color: {{ $ogc['bg'] }}; color: {{ $ogc['text'] }}; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 700;">{{ $student->overall_grade }}</span>
                        </td>
                        {{-- Rank --}}
                        <td style="padding: 5px 4px; text-align: center; font-weight: 800; color: #1e293b; background-color: #fefce8; font-size: 12px;">
                            {{ $student->rank }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>

                {{-- Subject Summary Footer --}}
                <tfoot>
                    <tr style="border-top: 3px solid #334155;">
                        <td colspan="3" style="padding: 8px 10px; font-weight: 700; color: #334155; position: sticky; left: 0; background-color: #f1f5f9; z-index: 10; border-right: 2px solid #cbd5e1; text-align: right; min-width: 320px;">
                            {{ __('Subject Summary:') }}
                        </td>
                        @foreach($previewSubjects as $subj)
                            @php $ss = $subjectSummaries[$subj->subject_id] ?? ['registered' => 0, 'with_marks' => 0, 'pass' => 0, 'fail' => 0]; @endphp
                            <td colspan="{{ $colsPerSubject }}" style="padding: 6px 4px; text-align: center; background-color: #f1f5f9; border-left: 2px solid #e2e8f0; font-size: 10px; line-height: 1.5; vertical-align: top;">
                                <span style="color: #64748b;">{{ __('Reg') }}: {{ $ss['registered'] }} | {{ __('Exam') }}: {{ $ss['with_marks'] }}</span><br>
                                @if($ss['with_marks'] > 0)
                                <span style="color: #059669; font-weight: 600;">P: {{ $ss['pass'] }} ({{ round(($ss['pass'] / $ss['with_marks']) * 100) }}%)</span><br>
                                <span style="color: #dc2626; font-weight: 600;">F: {{ $ss['fail'] }}</span>
                                @else
                                <span style="color: #94a3b8;">—</span>
                                @endif
                            </td>
                        @endforeach
                        <td colspan="4" style="padding: 8px; background-color: #f1f5f9; text-align: center;">
                            <span style="color: #059669; font-weight: 700;">{{ __('Pass') }}: {{ $previewPassCount }}</span> &nbsp;|&nbsp;
                            <span style="color: #dc2626; font-weight: 700;">{{ __('Fail') }}: {{ $previewFailCount }}</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Legend --}}
        <div style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 20px; display: flex; flex-wrap: wrap; gap: 8px 32px; font-size: 0.75rem; color: #64748b;">
            <div>
                <strong>{{ __('Column Key:') }}</strong>
                S1-S{{ $seqCount }} = {{ __('Sequence Scores') }} |
                AVG = {{ __('Average') }} |
                TOT = {{ __('Total Weighted') }} |
                Grd = {{ __('Grade') }} |
                CV = {{ __('Coefficient') }}
            </div>
            <div>
                <strong>{{ __('Status:') }}</strong>
                <span style="background-color: #059669; color: white; padding: 1px 6px; border-radius: 3px; font-weight: 600;">A+</span> {{ __('Pass (≥10)') }} |
                <span style="background-color: #dc2626; color: white; padding: 1px 6px; border-radius: 3px; font-weight: 600;">F</span> {{ __('Fail (<10)') }} |
                <span style="background-color: #1e293b; color: white; padding: 1px 6px; border-radius: 3px; font-weight: 600;">ABS</span> {{ __('Absent') }} |
                <span style="color: #cbd5e1; font-weight: 600;">-</span> {{ __('Not Registered') }}
            </div>
        </div>
        </div>{{-- /previewBody --}}
    </div>
    @endif

    @elseif($filtered)
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <div class="flex items-center">
            <svg class="w-5 h-5 text-yellow-500 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-yellow-800 text-sm font-medium">{{ __('No data found for the selected filters.') }}</span>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
const allForms = @json($forms->map(fn($f) => ['id' => $f->id, 'name' => $f->name, 'education_system' => $f->education_system]));
const baseUrl = @json(url('admin/exam-publishing'));

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
    clearSelect('termSelect', @json(__('— Select Term —')));
    clearSelect('sequenceSelect', @json(__('— Select Sequence —')));
});

// ── Form → Section + Term (AJAX) ──
document.getElementById('formSelect').addEventListener('change', function() {
    const formId = this.value;
    clearSelect('sectionSelect', @json(__('— Select Section —')));
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

filterFormsBySystem();

// ── Checkbox management ──
function updateSelectedCount() {
    const checked = document.querySelectorAll('.subject-checkbox:checked');
    const display = document.getElementById('selected_count_display');
    if (display) {
        display.textContent = checked.length + ' ' + @json(__('subject(s) selected'));
    }
    // Sync hidden inputs for bulk form
    const hiddenDiv = document.getElementById('hidden_submission_ids');
    if (hiddenDiv) {
        hiddenDiv.innerHTML = '';
        checked.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'submission_ids[]';
            input.value = cb.value;
            hiddenDiv.appendChild(input);
        });
    }
}

function toggleAllCheckboxes(checked) {
    document.querySelectorAll('.subject-checkbox').forEach(cb => { cb.checked = checked; });
    updateSelectedCount();
}

function selectAllCheckboxes() {
    toggleAllCheckboxes(true);
    const mc = document.getElementById('masterCheckbox');
    if (mc) mc.checked = true;
}

function deselectAllCheckboxes() {
    toggleAllCheckboxes(false);
    const mc = document.getElementById('masterCheckbox');
    if (mc) mc.checked = false;
}

function selectSameState() {
    // Prompt for which state to select
    const states = ['draft', 'submitted', 'approved', 'published'];
    const stateLabels = {
        draft: @json(__('Draft')),
        submitted: @json(__('Submitted')),
        approved: @json(__('Approved')),
        published: @json(__('Published'))
    };

    let msg = @json(__('Select all subjects with state:')) + '\n';
    states.forEach((s, i) => { msg += (i + 1) + '. ' + stateLabels[s] + '\n'; });
    const choice = prompt(msg);

    if (!choice) return;
    const idx = parseInt(choice) - 1;
    if (idx < 0 || idx >= states.length) return;

    const targetState = states[idx];
    deselectAllCheckboxes();
    document.querySelectorAll('.subject-checkbox').forEach(cb => {
        if (cb.dataset.status === targetState) cb.checked = true;
    });
    updateSelectedCount();
}

// Validate bulk form before submit
document.getElementById('bulkForm')?.addEventListener('submit', function(e) {
    const checked = document.querySelectorAll('.subject-checkbox:checked');
    const transition = document.getElementById('transitionSelect').value;

    if (checked.length === 0) {
        e.preventDefault();
        alert(@json(__('Please select at least one subject.')));
        return;
    }
    if (!transition) {
        e.preventDefault();
        alert(@json(__('Please select a transition state.')));
        return;
    }

    const stateLabels = { submitted: @json(__('Submitted')), approved: @json(__('Approved')), published: @json(__('Published')) };
    if (!confirm(@json(__('Transition')) + ' ' + checked.length + ' ' + @json(__('subject(s) to')) + ' "' + (stateLabels[transition] || transition) + '"?')) {
        e.preventDefault();
    }
});

// ── Toggle preview table (collapse/expand) ──
let previewExpanded = false;
function togglePreviewView() {
    const body = document.getElementById('previewBody');
    if (!body) return;
    previewExpanded = !previewExpanded;
    body.style.display = previewExpanded ? '' : 'none';
}
</script>
@endpush
