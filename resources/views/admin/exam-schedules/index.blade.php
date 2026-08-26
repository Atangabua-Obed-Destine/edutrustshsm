@extends('layouts.admin')

@section('title', __('Exam Schedule'))

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Exam Schedule') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('Schedule exams for each class section – set subjects, dates, times, rooms & invigilators') }}</p>
        </div>
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
        <form method="GET" action="{{ route('admin.exam-schedules.index') }}" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                {{-- Session --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Session') }} <span class="text-red-500">*</span></label>
                    <select name="academic_session_id" id="sessionSelect"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white"
                            style="appearance: auto; -webkit-appearance: menulist;" required>
                        <option value="">{{ __('— Select Session —') }}</option>
                        @foreach($sessions as $session)
                        <option value="{{ $session->id }}" {{ $sessionId == $session->id ? 'selected' : '' }}>
                            {{ $session->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Educational System --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Educational System') }} <span class="text-red-500">*</span></label>
                    <select name="education_system" id="eduSystemSelect"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white"
                            style="appearance: auto; -webkit-appearance: menulist;" required>
                        <option value="english" {{ ($educationSystem ?? 'english') === 'english' ? 'selected' : '' }}>{{ __('English') }}</option>
                        <option value="french" {{ ($educationSystem ?? '') === 'french' ? 'selected' : '' }}>{{ __('French') }}</option>
                    </select>
                </div>

                {{-- Form --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Form') }} <span class="text-red-500">*</span></label>
                    <select name="form_id" id="formSelect"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white"
                            style="appearance: auto; -webkit-appearance: menulist;" required>
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
                    <select name="class_section_id" id="sectionSelect"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white"
                            style="appearance: auto; -webkit-appearance: menulist;" required>
                        <option value="">{{ __('— Select Section —') }}</option>
                        @foreach($classSections as $cs)
                        <option value="{{ $cs->id }}" {{ $classSectionId == $cs->id ? 'selected' : '' }}>
                            {{ $cs->name }}
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

    {{-- Schedule Builder --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

        @if($filtered && $selectedSection)
        {{-- Section info bar --}}
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">{{ $selectedSection->name }}</h2>
                <p class="text-sm text-gray-500">{{ $selectedSection->form->name ?? '' }} &middot; {{ $sessions->firstWhere('id', $sessionId)->name ?? '' }}</p>
            </div>
            <div class="text-right">
                <p class="text-2xl font-bold text-slate-800">{{ $entries->count() }}</p>
                <p class="text-xs text-gray-500">{{ __('exam(s) scheduled') }}</p>
            </div>
        </div>

        {{-- Entry Form --}}
        <form method="POST" action="{{ route('admin.exam-schedules.save') }}" id="examForm" onsubmit="return validateExamForm()">
            @csrf
            <input type="hidden" name="academic_session_id" value="{{ $sessionId }}">
            <input type="hidden" name="class_section_id" value="{{ $classSectionId }}">
            <input type="hidden" name="education_system" value="{{ $educationSystem }}">

            <div class="p-6">
                {{-- Entries Container --}}
                <div id="entries-container" class="space-y-4">
                    @forelse($entries as $idx => $entry)
                    <div class="entry-row p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 items-end">
                            {{-- Subject --}}
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Subject') }} <span class="text-red-400">*</span></label>
                                <select name="entries[{{ $idx }}][subject_id]" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm"
                                        style="appearance: auto; -webkit-appearance: menulist;">
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach($subjects as $subj)
                                    <option value="{{ $subj->id }}" {{ $entry->subject_id == $subj->id ? 'selected' : '' }}>{{ $subj->name }} ({{ $subj->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            {{-- Invigilators --}}
                            <div class="col-span-2 md:col-span-1 lg:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Invigilator(s)') }} <span class="text-red-400">*</span></label>
                                <select name="entries[{{ $idx }}][invigilator_ids][]" multiple required
                                        class="invig-select w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm"
                                        style="min-height: 38px;" size="1">
                                    @foreach($teachers as $t)
                                    <option value="{{ $t->id }}" {{ $entry->invigilators->contains($t->id) ? 'selected' : '' }}>{{ $t->first_name }} {{ $t->last_name }}</option>
                                    @endforeach
                                </select>
                                <p class="text-[10px] text-gray-400 mt-0.5">{{ __('Ctrl+click for multiple') }}</p>
                            </div>
                            {{-- Room --}}
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Room') }}</label>
                                <select name="entries[{{ $idx }}][room_id]"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm"
                                        style="appearance: auto; -webkit-appearance: menulist;">
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach($rooms as $r)
                                    <option value="{{ $r->id }}" {{ $entry->room_id == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            {{-- Date --}}
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Date') }} <span class="text-red-400">*</span></label>
                                <input type="date" name="entries[{{ $idx }}][exam_date]" required
                                       value="{{ $entry->exam_date->format('Y-m-d') }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm">
                            </div>
                            {{-- Time From --}}
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Time From') }} <span class="text-red-400">*</span></label>
                                <input type="time" name="entries[{{ $idx }}][start_time]" required
                                       value="{{ \Carbon\Carbon::parse($entry->start_time)->format('H:i') }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm">
                            </div>
                            {{-- Time To + Remove --}}
                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Time To') }} <span class="text-red-400">*</span></label>
                                    <input type="time" name="entries[{{ $idx }}][end_time]" required
                                           value="{{ \Carbon\Carbon::parse($entry->end_time)->format('H:i') }}"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm">
                                </div>
                                <button type="button" onclick="removeRow(this)"
                                        class="shrink-0 inline-flex items-center px-2.5 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 text-sm transition"
                                        title="{{ __('Remove') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    @empty
                    {{-- Default empty row --}}
                    <div class="entry-row p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 items-end">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Subject') }} <span class="text-red-400">*</span></label>
                                <select name="entries[0][subject_id]" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm"
                                        style="appearance: auto; -webkit-appearance: menulist;">
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach($subjects as $subj)
                                    <option value="{{ $subj->id }}">{{ $subj->name }} ({{ $subj->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-2 md:col-span-1 lg:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Invigilator(s)') }} <span class="text-red-400">*</span></label>
                                <select name="entries[0][invigilator_ids][]" multiple required
                                        class="invig-select w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm"
                                        style="min-height: 38px;" size="1">
                                    @foreach($teachers as $t)
                                    <option value="{{ $t->id }}">{{ $t->first_name }} {{ $t->last_name }}</option>
                                    @endforeach
                                </select>
                                <p class="text-[10px] text-gray-400 mt-0.5">{{ __('Ctrl+click for multiple') }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Room') }}</label>
                                <select name="entries[0][room_id]"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm"
                                        style="appearance: auto; -webkit-appearance: menulist;">
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach($rooms as $r)
                                    <option value="{{ $r->id }}">{{ $r->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Date') }} <span class="text-red-400">*</span></label>
                                <input type="date" name="entries[0][exam_date]" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Time From') }} <span class="text-red-400">*</span></label>
                                <input type="time" name="entries[0][start_time]" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm">
                            </div>
                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Time To') }} <span class="text-red-400">*</span></label>
                                    <input type="time" name="entries[0][end_time]" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm">
                                </div>
                                <button type="button" onclick="removeRow(this)"
                                        class="shrink-0 inline-flex items-center px-2.5 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 text-sm transition"
                                        title="{{ __('Remove') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    @endforelse
                </div>

                {{-- Action Buttons --}}
                <div class="flex items-center justify-between mt-5 pt-5 border-t border-gray-200">
                    <button type="button" onclick="addRow()"
                            class="inline-flex items-center px-4 py-2.5 bg-green-500 text-white rounded-lg hover:bg-green-600 text-sm font-medium transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        {{ __('Add Exam') }}
                    </button>
                    <button type="submit"
                            class="inline-flex items-center px-6 py-2.5 bg-slate-800 text-white rounded-lg hover:bg-slate-700 text-sm font-medium transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        {{ __('Save Exam Schedule') }}
                    </button>
                </div>
            </div>
        </form>

        @else
        {{-- Empty state when not filtered --}}
        <div class="p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <h3 class="text-lg font-medium text-gray-600 mb-1">{{ __('Select a class section to begin') }}</h3>
            <p class="text-sm text-gray-400">{{ __('Choose a session, educational system, form and section above, then click Filter to load or create the exam schedule.') }}</p>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
    // JSON data for dynamic rows
    let subjectOptions = @json($subjects->map(fn($s) => ['id' => $s->id, 'label' => $s->name . ' (' . $s->code . ')']));
    const teacherOptions = @json($teachers->map(fn($t) => ['id' => $t->id, 'label' => trim($t->first_name . ' ' . $t->last_name)]));
    const roomOptions = @json($rooms->map(fn($r) => ['id' => $r->id, 'label' => $r->name]));
    const allForms = @json($forms->map(fn($f) => ['id' => $f->id, 'name' => $f->name, 'education_system' => $f->education_system]));
    const sectionsUrl = @json(url('admin/exam-schedules/sections-by-form'));
    const subjectsUrl = @json(url('admin/exam-schedules/subjects-by-form'));

    // ── Load button enable/disable ──
    function toggleLoadButton() {
        const btn = document.getElementById('loadBtn');
        const session = document.getElementById('sessionSelect').value;
        const form = document.getElementById('formSelect').value;
        const section = document.getElementById('sectionSelect').value;
        btn.disabled = !(session && form && section);
    }

    document.getElementById('sessionSelect').addEventListener('change', toggleLoadButton);
    document.getElementById('sectionSelect').addEventListener('change', toggleLoadButton);

    // Enable button on page load if all selections present
    toggleLoadButton();

    // ── Educational System → Form filtering ──
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

        // Clear downstream
        if (!formSelect.value) {
            document.getElementById('sectionSelect').innerHTML = '<option value="">' + @json(__('— Select Section —')) + '</option>';
        }
    }

    document.getElementById('eduSystemSelect').addEventListener('change', function() {
        filterFormsBySystem();
        document.getElementById('sectionSelect').innerHTML = '<option value="">' + @json(__('— Select Section —')) + '</option>';
        toggleLoadButton();
    });

    // ── Form → Section cascading ──
    document.getElementById('formSelect').addEventListener('change', function() {
        const formId = this.value;
        const sectionSelect = document.getElementById('sectionSelect');
        sectionSelect.innerHTML = '<option value="">' + @json(__('— Loading... —')) + '</option>';

        if (!formId) {
            sectionSelect.innerHTML = '<option value="">' + @json(__('— Select Section —')) + '</option>';
            return;
        }

        fetch(sectionsUrl + '/' + formId)
            .then(r => r.json())
            .then(sections => {
                let html = '<option value="">' + @json(__('— Select Section —')) + '</option>';
                sections.forEach(s => {
                    html += '<option value="' + s.id + '">' + escapeHtml(s.name) + '</option>';
                });
                sectionSelect.innerHTML = html;
                toggleLoadButton();
            })
            .catch(() => {
                sectionSelect.innerHTML = '<option value="">' + @json(__('— Select Section —')) + '</option>';
                toggleLoadButton();
            });
    });

    // ── Utility ──
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getNextIndex() {
        const container = document.getElementById('entries-container');
        if (!container) return 0;
        const rows = container.querySelectorAll('.entry-row');
        let max = -1;
        rows.forEach(row => {
            row.querySelectorAll('select, input').forEach(el => {
                const match = el.name && el.name.match(/entries\[(\d+)\]/);
                if (match) max = Math.max(max, parseInt(match[1]));
            });
        });
        return max + 1;
    }

    function buildSelect(name, options, required, placeholder) {
        let html = '<select name="' + name + '"' + (required ? ' required' : '') +
            ' class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm"' +
            ' style="appearance: auto; -webkit-appearance: menulist;">' +
            '<option value="">' + (placeholder || @json(__('Select'))) + '</option>';
        options.forEach(function(opt) {
            html += '<option value="' + opt.id + '">' + escapeHtml(opt.label) + '</option>';
        });
        html += '</select>';
        return html;
    }

    function buildMultiSelect(name, options, required) {
        let html = '<select name="' + name + '" multiple' + (required ? ' required' : '') +
            ' class="invig-select w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm"' +
            ' style="min-height: 38px;" size="1">';
        options.forEach(function(opt) {
            html += '<option value="' + opt.id + '">' + escapeHtml(opt.label) + '</option>';
        });
        html += '</select>' +
            '<p class="text-[10px] text-gray-400 mt-0.5">' + @json(__('Ctrl+click for multiple')) + '</p>';
        return html;
    }

    // ── Add Row ──
    function addRow() {
        const container = document.getElementById('entries-container');
        const empty = container.querySelector('.empty-state');
        if (empty) empty.remove();

        const idx = getNextIndex();
        const row = document.createElement('div');
        row.className = 'entry-row p-4 bg-blue-50/50 rounded-lg border border-blue-200';
        row.innerHTML =
            '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 items-end">' +
            '<div>' +
                '<label class="block text-xs font-medium text-gray-500 mb-1">' + @json(__('Subject')) + ' <span class="text-red-400">*</span></label>' +
                buildSelect('entries[' + idx + '][subject_id]', subjectOptions, true) +
            '</div>' +
            '<div class="col-span-2 md:col-span-1 lg:col-span-2">' +
                '<label class="block text-xs font-medium text-gray-500 mb-1">' + @json(__('Invigilator(s)')) + ' <span class="text-red-400">*</span></label>' +
                buildMultiSelect('entries[' + idx + '][invigilator_ids][]', teacherOptions, true) +
            '</div>' +
            '<div>' +
                '<label class="block text-xs font-medium text-gray-500 mb-1">' + @json(__('Room')) + '</label>' +
                buildSelect('entries[' + idx + '][room_id]', roomOptions, false) +
            '</div>' +
            '<div>' +
                '<label class="block text-xs font-medium text-gray-500 mb-1">' + @json(__('Date')) + ' <span class="text-red-400">*</span></label>' +
                '<input type="date" name="entries[' + idx + '][exam_date]" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm">' +
            '</div>' +
            '<div>' +
                '<label class="block text-xs font-medium text-gray-500 mb-1">' + @json(__('Time From')) + ' <span class="text-red-400">*</span></label>' +
                '<input type="time" name="entries[' + idx + '][start_time]" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm">' +
            '</div>' +
            '<div class="flex items-end gap-2">' +
                '<div class="flex-1">' +
                    '<label class="block text-xs font-medium text-gray-500 mb-1">' + @json(__('Time To')) + ' <span class="text-red-400">*</span></label>' +
                    '<input type="time" name="entries[' + idx + '][end_time]" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm">' +
                '</div>' +
                '<button type="button" onclick="removeRow(this)" class="shrink-0 inline-flex items-center px-2.5 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 text-sm transition" title="' + @json(__('Remove')) + '">' +
                    '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>' +
                '</button>' +
            '</div>' +
            '</div>';
        container.appendChild(row);
    }

    // ── Remove Row ──
    function removeRow(btn) {
        const row = btn.closest('.entry-row');
        const container = row.parentElement;
        row.remove();
        if (container.querySelectorAll('.entry-row').length === 0) {
            container.innerHTML =
                '<div class="empty-state text-center py-8 text-gray-400">' +
                    '<svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>' +
                    '<p class="text-sm">' + @json(__('No exams scheduled. Click + Add Exam to get started.')) + '</p>' +
                '</div>';
        }
    }

    // ── Validate ──
    function validateExamForm() {
        const rows = document.querySelectorAll('#entries-container .entry-row');
        if (rows.length === 0) {
            alert(@json(__('Please add at least one exam entry.')));
            return false;
        }
        // Check each row has at least one invigilator selected
        let valid = true;
        rows.forEach((row, i) => {
            const invigSelect = row.querySelector('.invig-select');
            if (invigSelect && invigSelect.selectedOptions.length === 0) {
                invigSelect.classList.add('border-red-500');
                valid = false;
            } else if (invigSelect) {
                invigSelect.classList.remove('border-red-500');
            }
        });
        if (!valid) {
            alert(@json(__('Please select at least one invigilator for each exam.')));
        }
        return valid;
    }

    // ── Init: filter forms by edu system on page load ──
    document.addEventListener('DOMContentLoaded', function() {
        filterFormsBySystem();

        // Re-select form if it was previously selected (via query param)
        @if($formId)
        const formSelect = document.getElementById('formSelect');
        formSelect.value = {{ $formId }};
        @endif
    });
</script>
@endpush
@endsection
