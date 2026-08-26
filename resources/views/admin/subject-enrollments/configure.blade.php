@extends('layouts.admin')

@section('title', __('Enroll Subjects') . ' — ' . $form->name)
@section('breadcrumb', __('Academic > Subject Enrollment') . ' > ' . $form->name)

@section('content')
<div class="max-w-6xl">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.subject-enrollments.index') }}"
               class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 transition">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                {{ __('Back') }}
            </a>
            <div>
                <h3 class="text-lg font-semibold text-gray-700">
                    {{ $form->name }}
                    <span class="font-mono text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded ml-2">{{ $form->short_name }}</span>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium ml-2 {{ $form->level === 'first_cycle' ? 'bg-sky-100 text-sky-700' : 'bg-violet-100 text-violet-700' }}">
                        {{ $form->level === 'first_cycle' ? __('1st Cycle') : __('2nd Cycle') }}
                    </span>
                </h3>
                <p class="text-sm text-gray-500 mt-0.5">{{ __('Configure which subjects are taught and their coefficients') }}</p>
            </div>
        </div>
        <button id="btn-save" onclick="saveEnrollments()" disabled
                class="px-5 py-2 bg-[#1e293b] text-white rounded-lg hover:bg-[#334155] transition text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span id="btn-save-text">{{ __('Save') }}</span>
        </button>
    </div>

    @if($streams->count() > 0)
    <!-- Stream Tabs -->
    <div class="mb-4">
        <div class="flex flex-wrap gap-2" id="stream-tabs">
            @foreach($streams as $stream)
            <button onclick="selectStream({{ $stream->id }})"
                    data-stream="{{ $stream->id }}"
                    class="stream-tab relative px-4 py-2 rounded-lg text-sm font-medium border transition
                           {{ $loop->first ? 'bg-[#1e293b] text-white border-[#1e293b]' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300 hover:bg-gray-50' }}">
                {{ $stream->name }}
                <span class="ml-1 text-xs opacity-70">({{ $stream->code }})</span>
                @if($stream->is_general)
                    <span class="ml-1 text-xs bg-blue-200 text-blue-800 px-1 rounded">{{ __('ALL CORE') }}</span>
                @endif
                <!-- Dirty indicator dot -->
                <span class="dirty-dot hidden absolute -top-1 -right-1 w-2.5 h-2.5 bg-amber-400 rounded-full border-2 border-white"></span>
                <!-- Count badge -->
                <span class="count-badge ml-1.5 text-xs opacity-60">0</span>
            </button>
            @endforeach
        </div>
    </div>
    @else
    <!-- No streams message -->
    <div class="bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-lg mb-4 text-sm">
        <strong>{{ __('No streams assigned.') }}</strong> {{ __('Subjects will be enrolled directly to this form without stream grouping.') }}
        <a href="{{ route('admin.forms.edit', $form) }}" class="underline ml-1">{{ __('Manage form streams') }}</a>
    </div>
    @endif

    <!-- Stats Bar -->
    <div class="bg-white rounded-lg border border-gray-200 px-4 py-3 mb-4 flex items-center justify-between">
        <div class="flex items-center gap-6 text-sm">
            <span class="text-gray-500">
                {{ __('Enrolled:') }} <strong id="stat-enrolled" class="text-gray-800">0</strong>
            </span>
            <span class="text-gray-500">
                {{ __('Core:') }} <strong id="stat-core" class="text-blue-600">0</strong>
            </span>
            <span class="text-gray-500">
                {{ __('Elective:') }} <strong id="stat-elective" class="text-orange-600">0</strong>
            </span>
            <span class="text-gray-500">
                {{ __('Total Coeff:') }} <strong id="stat-coeff" class="text-gray-800">0</strong>
            </span>
        </div>
        <div class="flex items-center gap-2">
            <span id="unsaved-badge" class="hidden text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium">
                {{ __('Unsaved changes') }}
            </span>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <div class="flex items-center gap-2">
            <button onclick="bulkAction('all-core')" class="px-3 py-1.5 text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200 rounded-lg hover:bg-blue-100 transition">
                {{ __('Select All Core') }}
            </button>
            <button onclick="bulkAction('all-elective')" class="px-3 py-1.5 text-xs font-medium bg-orange-50 text-orange-700 border border-orange-200 rounded-lg hover:bg-orange-100 transition">
                {{ __('Select All Elective') }}
            </button>
            <button onclick="bulkAction('clear')" class="px-3 py-1.5 text-xs font-medium bg-gray-50 text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-100 transition">
                {{ __('Clear All') }}
            </button>
        </div>
        <div class="relative">
            <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" id="search-input" placeholder="{{ __('Search subjects...') }}"
                   oninput="filterSubjects(this.value)"
                   class="pl-9 pr-4 py-1.5 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-56">
        </div>
    </div>

    <!-- Subject Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm" id="subject-table">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="w-12 px-4 py-3 text-center">
                        <input type="checkbox" id="check-all" onchange="toggleAll(this.checked)" class="rounded border-gray-300">
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Subject') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Code') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Department') }}</th>
                    <th class="w-28 px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">{{ __('Coefficient') }}</th>
                    <th class="w-32 px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">{{ __('Type') }}</th>
                    <th class="w-48 px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Subject Master') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100" id="subject-body">
                @forelse($subjects as $subject)
                <tr class="subject-row hover:bg-gray-50 transition-colors"
                    data-subject-id="{{ $subject->id }}"
                    data-name="{{ strtolower($subject->name . ' ' . $subject->code . ' ' . ($subject->department?->name ?? '')) }}">
                    <td class="px-4 py-3 text-center">
                        <input type="checkbox" class="subj-check rounded border-gray-300"
                               data-sid="{{ $subject->id }}"
                               onchange="toggleSubject({{ $subject->id }}, this.checked)">
                    </td>
                    <td class="px-4 py-3">
                        <span class="font-medium text-gray-800">{{ $subject->name }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="font-mono text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded">{{ $subject->code }}</span>
                    </td>
                    <td class="px-4 py-3 text-gray-500">{{ $subject->department?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-center">
                        <input type="number" min="1" max="9" step="1"
                               class="coeff-input w-16 text-center text-sm border border-gray-200 rounded-lg py-1 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed"
                               data-sid="{{ $subject->id }}"
                               value=""
                               placeholder="—"
                               disabled
                               oninput="updateCoefficient({{ $subject->id }}, this.value)"
                               onchange="updateCoefficient({{ $subject->id }}, this.value)">
                    </td>
                    <td class="px-4 py-3 text-center">
                        <select class="type-select w-28 text-sm border border-gray-200 rounded-lg py-1.5 px-2 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed"
                                style="appearance: auto; -webkit-appearance: menulist;"
                                data-sid="{{ $subject->id }}"
                                disabled
                                onchange="updateType({{ $subject->id }}, this.value)">
                            <option value="core">{{ __('Core') }}</option>
                            <option value="elective">{{ __('Elective') }}</option>
                        </select>
                    </td>
                    <td class="px-4 py-3">
                        <select class="master-select w-44 text-sm border border-gray-200 rounded-lg py-1.5 px-2 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed"
                                style="appearance: auto; -webkit-appearance: menulist;"
                                data-sid="{{ $subject->id }}"
                                disabled
                                onchange="updateMaster({{ $subject->id }}, this.value)">
                            <option value="">— {{ __('None') }} —</option>
                        </select>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-gray-400">
                        {{ __('No subjects found.') }} <a href="{{ route('admin.subjects.create') }}" class="text-blue-600 hover:underline">{{ __('Create subjects first') }}</a>.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Bottom Save -->
    @if($subjects->count() > 0)
    <div class="mt-4 flex justify-end">
        <button onclick="saveEnrollments()"
                class="px-5 py-2 bg-[#1e293b] text-white rounded-lg hover:bg-[#334155] transition text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                id="btn-save-bottom">
            {{ __('Save Changes') }}
        </button>
    </div>
    @endif
</div>

<!-- Toast Notification -->
<div id="toast" class="fixed bottom-6 right-6 z-50 hidden transform transition-all duration-300 translate-y-4 opacity-0">
    <div class="flex items-center gap-3 px-5 py-3 rounded-lg shadow-lg border" id="toast-inner">
        <svg id="toast-icon-success" class="w-5 h-5 text-emerald-500 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <svg id="toast-icon-error" class="w-5 h-5 text-red-500 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        <span id="toast-message" class="text-sm font-medium"></span>
    </div>
</div>

<script>
// ── Server Data ──
const formId = {{ $form->id }};
const subjects = @json($subjectsJson);
const streams = @json($streamsJson);
const serverEnrollments = @json($enrollments);
const subjectTeachers = @json($subjectTeachers);
const hasStreams = streams.length > 0;
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

// ── State ──
// enrollmentMap: { streamKey: { subjectId: { coefficient, type, subject_master_id } } }
let enrollmentMap = {};
let currentStreamId = hasStreams ? streams[0].id : null;
let dirtyStreams = new Set();
let saving = false;

// ── Helper: explicitly toggle disabled state ──
function setDisabled(el, disabled) {
    el.disabled = disabled;
    if (disabled) {
        el.setAttribute('disabled', '');
    } else {
        el.removeAttribute('disabled');
    }
}

// ── Initialize ──
function init() {
    // Populate teacher options per subject from timetable data
    document.querySelectorAll('.master-select').forEach(sel => {
        const sid = sel.dataset.sid;
        const teacherList = subjectTeachers[sid] || [];
        teacherList.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = t.name;
            sel.appendChild(opt);
        });
    });

    // Build enrollment map from server data
    serverEnrollments.forEach(e => {
        const sk = e.stream_id ? String(e.stream_id) : 'null';
        if (!enrollmentMap[sk]) enrollmentMap[sk] = {};
        enrollmentMap[sk][String(e.subject_id)] = {
            coefficient: parseFloat(e.coefficient),
            type: e.type,
            subject_master_id: e.subject_master_id ? parseInt(e.subject_master_id) : null
        };
    });

    // Render the first stream (or null stream)
    renderStream();
    updateStreamCounts();
}

// ── Sync current stream DOM values into enrollmentMap ──
function syncCurrentStream() {
    const sk = getStreamKey();
    if (!enrollmentMap[sk]) return;

    document.querySelectorAll('.subject-row').forEach(row => {
        const sid = row.dataset.subjectId;
        if (!enrollmentMap[sk][sid]) return;

        const coeffInput = row.querySelector('.coeff-input');
        const typeSelect = row.querySelector('.type-select');
        const masterSelect = row.querySelector('.master-select');
        const num = parseFloat(coeffInput.value);
        if (!isNaN(num) && num >= 1 && num <= 9) {
            enrollmentMap[sk][sid].coefficient = num;
        }
        enrollmentMap[sk][sid].type = typeSelect.value;
        enrollmentMap[sk][sid].subject_master_id = masterSelect.value ? parseInt(masterSelect.value) : null;
    });
}

// ── Stream Selection ──
function selectStream(streamId) {
    syncCurrentStream();
    currentStreamId = streamId;
    renderStream();

    // Update tab visuals
    document.querySelectorAll('.stream-tab').forEach(tab => {
        const tabStreamId = parseInt(tab.dataset.stream);
        if (tabStreamId === streamId) {
            tab.classList.add('bg-[#1e293b]', 'text-white', 'border-[#1e293b]');
            tab.classList.remove('bg-white', 'text-gray-600', 'border-gray-200', 'hover:border-gray-300', 'hover:bg-gray-50');
        } else {
            tab.classList.remove('bg-[#1e293b]', 'text-white', 'border-[#1e293b]');
            tab.classList.add('bg-white', 'text-gray-600', 'border-gray-200', 'hover:border-gray-300', 'hover:bg-gray-50');
        }
    });
}

function getStreamKey() {
    return currentStreamId ? String(currentStreamId) : 'null';
}

function getCurrentStreamObj() {
    return streams.find(s => s.id === currentStreamId) || null;
}

// ── Render Subject Table for Current Stream ──
function renderStream() {
    const sk = getStreamKey();
    const streamData = enrollmentMap[sk] || {};
    const streamObj = getCurrentStreamObj();
    const isGeneral = streamObj ? streamObj.is_general : false;

    document.querySelectorAll('.subject-row').forEach(row => {
        const sid = row.dataset.subjectId;
        const data = streamData[sid];
        const enrolled = !!data;

        const checkbox = row.querySelector('.subj-check');
        const coeffInput = row.querySelector('.coeff-input');
        const typeSelect = row.querySelector('.type-select');
        const masterSelect = row.querySelector('.master-select');

        checkbox.checked = enrolled;
        coeffInput.value = data ? data.coefficient : '';
        setDisabled(coeffInput, !enrolled);
        typeSelect.value = data ? data.type : 'core';
        setDisabled(typeSelect, !enrolled || isGeneral);
        masterSelect.value = (data && data.subject_master_id) ? data.subject_master_id : '';
        setDisabled(masterSelect, !enrolled);

        // If general stream, force core
        if (isGeneral && enrolled) {
            typeSelect.value = 'core';
        }
    });

    updateStats();
    updateCheckAll();
}

// ── Subject Toggle ──
function toggleSubject(subjectId, checked) {
    const sk = getStreamKey();
    if (!enrollmentMap[sk]) enrollmentMap[sk] = {};

    const row = document.querySelector(`tr[data-subject-id="${subjectId}"]`);
    const coeffInput = row.querySelector('.coeff-input');
    const typeSelect = row.querySelector('.type-select');
    const masterSelect = row.querySelector('.master-select');
    const streamObj = getCurrentStreamObj();
    const isGeneral = streamObj ? streamObj.is_general : false;

    if (checked) {
        if (!coeffInput.value || coeffInput.value === '') coeffInput.value = 1;
        const coeff = parseFloat(coeffInput.value) || 1;
        enrollmentMap[sk][String(subjectId)] = {
            coefficient: coeff,
            type: isGeneral ? 'core' : (typeSelect.value || 'core'),
            subject_master_id: masterSelect.value ? parseInt(masterSelect.value) : null
        };
        setDisabled(coeffInput, false);
        setDisabled(typeSelect, isGeneral);
        setDisabled(masterSelect, false);
        if (isGeneral) typeSelect.value = 'core';
    } else {
        delete enrollmentMap[sk][String(subjectId)];
        coeffInput.value = '';
        setDisabled(coeffInput, true);
        setDisabled(typeSelect, true);
        masterSelect.value = '';
        setDisabled(masterSelect, true);
    }

    markDirty();
    updateStats();
    updateCheckAll();
}

// ── Toggle All ──
function toggleAll(checked) {
    document.querySelectorAll('.subject-row').forEach(row => {
        if (row.style.display === 'none') return; // skip filtered out
        const sid = row.dataset.subjectId;
        const checkbox = row.querySelector('.subj-check');
        if (checkbox.checked !== checked) {
            checkbox.checked = checked;
            toggleSubject(parseInt(sid), checked);
        }
    });
}

function updateCheckAll() {
    const visible = document.querySelectorAll('.subject-row:not([style*="display: none"])');
    const checked = document.querySelectorAll('.subject-row:not([style*="display: none"]) .subj-check:checked');
    document.getElementById('check-all').checked = visible.length > 0 && visible.length === checked.length;
}

// ── Coefficient Update ──
function updateCoefficient(subjectId, value) {
    const sk = getStreamKey();
    if (!enrollmentMap[sk]?.[String(subjectId)]) return;
    const num = parseFloat(value);
    if (isNaN(num) || num < 1 || num > 9) return;
    enrollmentMap[sk][String(subjectId)].coefficient = num;
    markDirty();
    updateStats();
}

// ── Type Update ──
function updateType(subjectId, value) {
    const sk = getStreamKey();
    if (!enrollmentMap[sk]?.[String(subjectId)]) return;
    enrollmentMap[sk][String(subjectId)].type = value;
    markDirty();
    updateStats();
}

// ── Subject Master Update ──
function updateMaster(subjectId, value) {
    const sk = getStreamKey();
    if (!enrollmentMap[sk]?.[String(subjectId)]) return;
    enrollmentMap[sk][String(subjectId)].subject_master_id = value ? parseInt(value) : null;
    markDirty();
}

// ── Bulk Actions ──
function bulkAction(action) {
    const sk = getStreamKey();
    if (!enrollmentMap[sk]) enrollmentMap[sk] = {};
    const streamObj = getCurrentStreamObj();
    const isGeneral = streamObj ? streamObj.is_general : false;

    document.querySelectorAll('.subject-row').forEach(row => {
        if (row.style.display === 'none') return;
        const sid = row.dataset.subjectId;
        const checkbox = row.querySelector('.subj-check');
        const coeffInput = row.querySelector('.coeff-input');
        const typeSelect = row.querySelector('.type-select');

        if (action === 'clear') {
            checkbox.checked = false;
            setDisabled(coeffInput, true);
            setDisabled(typeSelect, true);
            const masterSel = row.querySelector('.master-select');
            masterSel.value = '';
            setDisabled(masterSel, true);
            delete enrollmentMap[sk][sid];
        } else {
            const type = action === 'all-core' ? 'core' : 'elective';
            checkbox.checked = true;
            setDisabled(coeffInput, false);
            setDisabled(typeSelect, isGeneral);
            setDisabled(row.querySelector('.master-select'), false);
            typeSelect.value = isGeneral ? 'core' : type;
            const coeff = parseFloat(coeffInput.value) || 1;
            const existingMaster = enrollmentMap[sk]?.[sid]?.subject_master_id || null;
            enrollmentMap[sk][sid] = {
                coefficient: coeff,
                type: isGeneral ? 'core' : type,
                subject_master_id: existingMaster
            };
        }
    });

    markDirty();
    updateStats();
    updateCheckAll();
}

// ── Search / Filter ──
function filterSubjects(term) {
    const lower = term.toLowerCase();
    document.querySelectorAll('.subject-row').forEach(row => {
        const name = row.dataset.name;
        row.style.display = name.includes(lower) ? '' : 'none';
    });
    updateCheckAll();
}

// ── Stats ──
function updateStats() {
    const sk = getStreamKey();
    const streamData = enrollmentMap[sk] || {};
    const entries = Object.values(streamData);

    const enrolled = entries.length;
    const core = entries.filter(e => e.type === 'core').length;
    const elective = entries.filter(e => e.type === 'elective').length;
    const totalCoeff = entries.reduce((sum, e) => sum + e.coefficient, 0);

    document.getElementById('stat-enrolled').textContent = enrolled;
    document.getElementById('stat-core').textContent = core;
    document.getElementById('stat-elective').textContent = elective;
    document.getElementById('stat-coeff').textContent = totalCoeff;
}

// ── Stream Counts (badges on tabs) ──
function updateStreamCounts() {
    document.querySelectorAll('.stream-tab').forEach(tab => {
        const sid = tab.dataset.stream;
        const data = enrollmentMap[sid] || {};
        const count = Object.keys(data).length;
        tab.querySelector('.count-badge').textContent = count;
    });
}

// ── Dirty State ──
function markDirty() {
    const sk = getStreamKey();
    dirtyStreams.add(sk);
    updateDirtyUI();
}

function updateDirtyUI() {
    const hasDirty = dirtyStreams.size > 0;

    // Save buttons
    document.getElementById('btn-save').disabled = !hasDirty;
    document.getElementById('btn-save-bottom').disabled = !hasDirty;

    // Unsaved badge
    document.getElementById('unsaved-badge').classList.toggle('hidden', !hasDirty);

    // Dirty dots on stream tabs
    document.querySelectorAll('.stream-tab').forEach(tab => {
        const sid = tab.dataset.stream;
        const dot = tab.querySelector('.dirty-dot');
        if (dot) dot.classList.toggle('hidden', !dirtyStreams.has(sid));
    });

    updateStreamCounts();
}

// ── Save ──
async function saveEnrollments() {
    if (saving || dirtyStreams.size === 0) return;
    syncCurrentStream();
    saving = true;

    const btnSave = document.getElementById('btn-save');
    const btnSaveBottom = document.getElementById('btn-save-bottom');
    const btnText = document.getElementById('btn-save-text');
    btnText.textContent = '{{ __("Saving...") }}';
    btnSave.disabled = true;
    btnSaveBottom.disabled = true;

    let allSuccess = true;
    let totalSaved = 0;

    // Save each dirty stream
    for (const sk of dirtyStreams) {
        const streamId = sk === 'null' ? null : parseInt(sk);
        const streamData = enrollmentMap[sk] || {};

        // Build subjects array
        const subjectsPayload = Object.entries(streamData).map(([subjectId, data]) => ({
            subject_id: parseInt(subjectId),
            coefficient: data.coefficient,
            type: data.type,
            subject_master_id: data.subject_master_id || null
        }));

        try {
            const resp = await fetch(`{{ url('admin/subject-enrollments') }}/${formId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    stream_id: streamId,
                    subjects: subjectsPayload
                })
            });

            const data = await resp.json();
            if (data.success) {
                totalSaved += data.count;
            } else {
                allSuccess = false;
            }
        } catch (e) {
            allSuccess = false;
        }
    }

    saving = false;
    btnText.textContent = '{{ __("Save") }}';

    if (allSuccess) {
        dirtyStreams.clear();
        updateDirtyUI();
        showToast('success', `{{ __("Saved successfully!") }} ${totalSaved} {{ __("subject(s) enrolled.") }}`);
    } else {
        showToast('error', '{{ __("Some changes could not be saved. Please try again.") }}');
        updateDirtyUI();
    }
}

// ── Toast ──
function showToast(type, message) {
    const toast = document.getElementById('toast');
    const inner = document.getElementById('toast-inner');
    const msgEl = document.getElementById('toast-message');
    const iconSuccess = document.getElementById('toast-icon-success');
    const iconError = document.getElementById('toast-icon-error');

    msgEl.textContent = message;
    iconSuccess.classList.toggle('hidden', type !== 'success');
    iconError.classList.toggle('hidden', type !== 'error');

    if (type === 'success') {
        inner.className = 'flex items-center gap-3 px-5 py-3 rounded-lg shadow-lg border bg-emerald-50 border-emerald-200 text-emerald-800';
    } else {
        inner.className = 'flex items-center gap-3 px-5 py-3 rounded-lg shadow-lg border bg-red-50 border-red-200 text-red-800';
    }

    toast.classList.remove('hidden');
    requestAnimationFrame(() => {
        toast.classList.remove('translate-y-4', 'opacity-0');
        toast.classList.add('translate-y-0', 'opacity-100');
    });

    setTimeout(() => {
        toast.classList.remove('translate-y-0', 'opacity-100');
        toast.classList.add('translate-y-4', 'opacity-0');
        setTimeout(() => toast.classList.add('hidden'), 300);
    }, 3000);
}

// ── Prevent Accidental Navigation ──
window.addEventListener('beforeunload', function(e) {
    if (dirtyStreams.size > 0) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// ── Init ──
document.addEventListener('DOMContentLoaded', init);
</script>
@endsection
