@extends('layouts.admin')

@section('title', __('Subject Add/Drop'))
@section('breadcrumb', __('Students') . ' > ' . __('Subject Add/Drop'))

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('Subject Add/Drop') }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ __('Search for a student, then add or drop elective subjects for their current enrollment') }}</p>
    </div>

    @if(!$currentSession)
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <div class="flex items-center">
            <svg class="w-5 h-5 text-yellow-500 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-yellow-800 text-sm font-medium">{{ __('No active academic session found. Please activate an academic session first.') }}</span>
        </div>
    </div>
    @else

    {{-- Search Section --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="border-l-4 border-blue-500 pl-4 mb-5">
            <h2 class="text-lg font-semibold text-gray-800">{{ __('Student Search') }}</h2>
        </div>

        <div class="flex flex-col sm:flex-row gap-4 items-end">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Student ID / Name') }} <span class="text-red-500">*</span></label>
                <div class="relative">
                    <select id="student_select" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" style="appearance: auto; -webkit-appearance: menulist;">
                        <option value="">{{ __('— Select Student —') }}</option>
                        @foreach($students as $s)
                        <option value="{{ $s->id }}">{{ $s->student_id }} — {{ $s->full_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <button type="button" id="btn_search" onclick="searchStudent()"
                    style="background-color: #0ea5e9; color: white; padding: 10px 28px; border-radius: 8px; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer;"
                    onmouseover="this.style.backgroundColor='#0284c7'"
                    onmouseout="this.style.backgroundColor='#0ea5e9'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    {{ __('Search') }}
                </button>
            </div>
        </div>

        {{-- Search hint --}}
        <p class="text-xs text-gray-400 mt-2">
            <svg class="w-3.5 h-3.5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ __('Type student ID or name to filter, then click Search') }}
        </p>
    </div>

    {{-- Loading Spinner --}}
    <div id="loading_spinner" class="hidden">
        <div class="flex justify-center items-center py-12">
            <svg class="animate-spin h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span class="ml-3 text-gray-500 font-medium">{{ __('Loading student data...') }}</span>
        </div>
    </div>

    {{-- Error Alert --}}
    <div id="error_alert" class="hidden bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="flex items-center">
            <svg class="w-5 h-5 text-red-500 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span id="error_text" class="text-red-800 text-sm font-medium"></span>
        </div>
    </div>

    {{-- Success Alert --}}
    <div id="success_alert" class="hidden bg-green-50 border border-green-200 rounded-lg p-4">
        <div class="flex items-center">
            <svg class="w-5 h-5 text-green-500 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span id="success_text" class="text-green-800 text-sm font-medium"></span>
        </div>
    </div>

    {{-- Student Info + Subjects Container (hidden until search) --}}
    <div id="student_results" class="hidden space-y-6">

        {{-- Student Info Card --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-100">
                {{-- Basic Info --}}
                <div class="p-5">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3">{{ __('Basic Info') }}</h3>
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-500">{{ __('Student ID') }}:</span>
                            <span id="info_student_id" class="text-sm font-bold text-blue-600"></span>
                            <span id="info_status_badge" class="text-xs px-2 py-0.5 rounded-full font-semibold" style="background-color: #d1fae5; color: #065f46;"></span>
                        </div>
                        <div>
                            <span class="text-sm text-gray-500">{{ __('Name') }}:</span>
                            <span id="info_name" class="text-sm font-semibold text-gray-800 ml-1"></span>
                        </div>
                        <div>
                            <span class="text-sm text-gray-500">{{ __('Gender') }}:</span>
                            <span id="info_gender" class="text-sm text-gray-800 ml-1 capitalize"></span>
                        </div>
                    </div>
                </div>
                {{-- Academic Info --}}
                <div class="p-5">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3">{{ __('Academic Information') }}</h3>
                    <div class="space-y-2">
                        <div>
                            <span class="text-sm text-gray-500">{{ __('Session') }}:</span>
                            <span id="info_session" class="text-sm text-gray-800 ml-1"></span>
                        </div>
                        <div>
                            <span class="text-sm text-gray-500">{{ __('Form') }}:</span>
                            <span id="info_form" class="text-sm font-semibold text-gray-800 ml-1"></span>
                        </div>
                        <div class="flex gap-6">
                            <div>
                                <span class="text-sm text-gray-500">{{ __('Stream') }}:</span>
                                <span id="info_stream" class="text-sm text-gray-800 ml-1"></span>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">{{ __('Section') }}:</span>
                                <span id="info_section" class="text-sm text-gray-800 ml-1"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stats Bar --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg border border-gray-200 p-4 text-center">
                <p class="text-2xl font-bold text-gray-800" id="stat_total">0</p>
                <p class="text-xs text-gray-500 mt-1">{{ __('Total Subjects') }}</p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4 text-center">
                <p class="text-2xl font-bold" id="stat_core" style="color: #0369a1;">0</p>
                <p class="text-xs text-gray-500 mt-1">{{ __('Core Subjects') }}</p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4 text-center">
                <p class="text-2xl font-bold" id="stat_elective" style="color: #7c3aed;">0</p>
                <p class="text-xs text-gray-500 mt-1">{{ __('Elective Subjects') }}</p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4 text-center">
                <p class="text-2xl font-bold text-gray-800" id="stat_coefficient">0</p>
                <p class="text-xs text-gray-500 mt-1">{{ __('Total Coefficient') }}</p>
            </div>
        </div>

        {{-- Current Enrolled Subjects Table --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="border-l-4 border-blue-500 pl-4 pr-4 py-4 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800">{{ __('Enrolled Subjects') }}</h2>
                    <p class="text-xs text-gray-400 mt-0.5" id="enrolled_subtitle"></p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="background-color: #0ea5e9; color: white;">
                            <th class="px-4 py-3 text-left font-semibold">#</th>
                            <th class="px-4 py-3 text-left font-semibold">{{ __('Code') }}</th>
                            <th class="px-4 py-3 text-left font-semibold">{{ __('Subject') }}</th>
                            <th class="px-4 py-3 text-center font-semibold">{{ __('Coefficient') }}</th>
                            <th class="px-4 py-3 text-center font-semibold">{{ __('Type') }}</th>
                            <th class="px-4 py-3 text-center font-semibold">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody id="enrolled_tbody">
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-400">
                                {{ __('Search for a student to view their subjects') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Available Electives Section --}}
        <div id="electives_section" class="hidden bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="border-l-4 border-purple-500 pl-4 pr-4 py-4 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800">{{ __('Available Elective Subjects') }}</h2>
                    <p class="text-xs text-gray-400 mt-0.5" id="electives_subtitle"></p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="background-color: #8b5cf6; color: white;">
                            <th class="px-4 py-3 text-left font-semibold">#</th>
                            <th class="px-4 py-3 text-left font-semibold">{{ __('Code') }}</th>
                            <th class="px-4 py-3 text-left font-semibold">{{ __('Subject') }}</th>
                            <th class="px-4 py-3 text-center font-semibold">{{ __('Coefficient') }}</th>
                            <th class="px-4 py-3 text-center font-semibold">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody id="electives_tbody">
                    </tbody>
                </table>
            </div>
        </div>

        {{-- No Subjects Configured Warning --}}
        <div id="no_subjects_warning" class="hidden bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-yellow-500 mr-3 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    <span class="text-yellow-800 text-sm font-medium">{{ __('No subjects have been configured for this student\'s form and stream.') }}</span>
                    <p class="text-yellow-700 text-xs mt-1">{{ __('Please configure subjects via Academic > Enroll Subjects before using Subject Add/Drop.') }}</p>
                </div>
            </div>
        </div>

    </div>

    @endif
</div>
@endsection

@push('scripts')
<script>
const baseUrl = @json(url('admin/subject-add-drop'));
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
let currentEnrollmentId = null;

// =========================================================================
// Searchable select - make the student dropdown filterable
// =========================================================================
(function() {
    const select = document.getElementById('student_select');
    if (!select) return;

    const wrapper = select.parentElement;
    const options = Array.from(select.options);

    // Create custom dropdown
    const container = document.createElement('div');
    container.className = 'relative';
    container.style.cssText = 'position: relative;';

    // Display button
    const display = document.createElement('button');
    display.type = 'button';
    display.className = 'w-full px-4 py-2.5 border border-gray-300 rounded-lg bg-white text-left flex items-center justify-between outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500';
    display.innerHTML = `<span class="truncate text-gray-500">${@json(__('— Select Student —'))}</span><svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>`;

    // Dropdown panel
    const panel = document.createElement('div');
    panel.className = 'absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg';
    panel.style.cssText = 'display: none; max-height: 300px; overflow: hidden;';

    // Search input inside dropdown
    const searchBox = document.createElement('div');
    searchBox.className = 'p-2 border-b border-gray-100';
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = @json(__('Search by ID or name...'));
    searchInput.className = 'w-full px-3 py-2 border border-gray-300 rounded-md text-sm outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500';
    searchBox.appendChild(searchInput);

    // Options list
    const optionsList = document.createElement('div');
    optionsList.style.cssText = 'max-height: 240px; overflow-y: auto;';

    panel.appendChild(searchBox);
    panel.appendChild(optionsList);
    container.appendChild(display);
    container.appendChild(panel);

    select.style.display = 'none';
    wrapper.appendChild(container);

    function renderOptions(filter = '') {
        optionsList.innerHTML = '';
        const lower = filter.toLowerCase();
        let count = 0;

        options.forEach(opt => {
            if (!opt.value && filter) return; // skip placeholder when filtering
            const text = opt.textContent;
            if (filter && !text.toLowerCase().includes(lower)) return;

            const item = document.createElement('div');
            item.className = 'px-4 py-2.5 cursor-pointer text-sm hover:bg-blue-50';
            if (opt.value === select.value) {
                item.style.cssText = 'background-color: #dbeafe; font-weight: 600;';
            }
            if (!opt.value) {
                item.style.cssText = 'color: #9ca3af;';
            }
            item.textContent = text;
            item.addEventListener('click', () => {
                select.value = opt.value;
                display.querySelector('span').textContent = opt.value ? text : @json(__('— Select Student —'));
                display.querySelector('span').className = opt.value ? 'truncate text-gray-800 font-medium' : 'truncate text-gray-500';
                panel.style.display = 'none';
            });
            optionsList.appendChild(item);
            count++;
        });

        if (count === 0) {
            const empty = document.createElement('div');
            empty.className = 'px-4 py-6 text-center text-sm text-gray-400';
            empty.textContent = @json(__('No students found'));
            optionsList.appendChild(empty);
        }
    }

    display.addEventListener('click', () => {
        const isOpen = panel.style.display !== 'none';
        panel.style.display = isOpen ? 'none' : 'block';
        if (!isOpen) {
            searchInput.value = '';
            renderOptions();
            setTimeout(() => searchInput.focus(), 50);
        }
    });

    searchInput.addEventListener('input', () => {
        renderOptions(searchInput.value);
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
        if (!container.contains(e.target)) {
            panel.style.display = 'none';
        }
    });

    renderOptions();
})();

// =========================================================================
// Search student
// =========================================================================
async function searchStudent() {
    const select = document.getElementById('student_select');
    const studentId = select.value;

    if (!studentId) {
        showError(@json(__('Please select a student first.')));
        return;
    }

    hideAlerts();
    document.getElementById('loading_spinner').classList.remove('hidden');
    document.getElementById('student_results').classList.add('hidden');

    try {
        const res = await fetch(`${baseUrl}/load/${studentId}`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        });
        const data = await res.json();

        document.getElementById('loading_spinner').classList.add('hidden');

        if (!res.ok) {
            showError(data.error || @json(__('Failed to load student data.')));
            return;
        }

        currentEnrollmentId = data.enrollment.id;
        renderStudentInfo(data);
        renderStats(data.stats);
        renderEnrolledSubjects(data.enrolled_subjects);
        renderAvailableElectives(data.available_electives);

        document.getElementById('student_results').classList.remove('hidden');

        // Show warning if no subjects configured at all
        const noSubjects = data.enrolled_subjects.length === 0 && data.available_electives.length === 0;
        document.getElementById('no_subjects_warning').classList.toggle('hidden', !noSubjects);

    } catch (e) {
        document.getElementById('loading_spinner').classList.add('hidden');
        showError(@json(__('Network error. Please check your connection and try again.')));
        console.error(e);
    }
}

// =========================================================================
// Render student info
// =========================================================================
function renderStudentInfo(data) {
    document.getElementById('info_student_id').textContent = '#' + data.student.student_id;
    document.getElementById('info_name').textContent = data.student.full_name;
    document.getElementById('info_gender').textContent = data.student.gender;
    document.getElementById('info_status_badge').textContent = data.student.status.toUpperCase();
    document.getElementById('info_session').textContent = data.enrollment.session;
    document.getElementById('info_form').textContent = data.enrollment.form;
    document.getElementById('info_stream').textContent = data.enrollment.stream || '—';
    document.getElementById('info_section').textContent = data.enrollment.section;
}

// =========================================================================
// Render stats
// =========================================================================
function renderStats(stats) {
    document.getElementById('stat_total').textContent = stats.total_enrolled;
    document.getElementById('stat_core').textContent = stats.core_count;
    document.getElementById('stat_elective').textContent = stats.elective_count;
    document.getElementById('stat_coefficient').textContent = parseFloat(stats.total_coefficient).toFixed(1);
}

// =========================================================================
// Render enrolled subjects table
// =========================================================================
function renderEnrolledSubjects(subjects) {
    const tbody = document.getElementById('enrolled_tbody');
    const subtitle = document.getElementById('enrolled_subtitle');

    if (subjects.length === 0) {
        subtitle.textContent = @json(__('No subjects enrolled yet'));
        tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">${@json(__('No subjects enrolled. Core subjects will be auto-enrolled when available.'))}</td></tr>`;
        return;
    }

    subtitle.textContent = subjects.length + ' ' + @json(__('subjects enrolled for current session'));
    tbody.innerHTML = '';

    subjects.forEach((s, i) => {
        const isCore = s.type === 'core';
        const typeBadge = isCore
            ? `<span style="background-color: #dbeafe; color: #1e40af; padding: 2px 10px; border-radius: 9999px; font-size: 11px; font-weight: 600;">${@json(__('Core'))}</span>`
            : `<span style="background-color: #ede9fe; color: #5b21b6; padding: 2px 10px; border-radius: 9999px; font-size: 11px; font-weight: 600;">${@json(__('Elective'))}</span>`;

        const actionHtml = isCore
            ? `<span style="color: #9ca3af; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                ${@json(__('Locked'))}
               </span>`
            : `<button onclick="dropSubject(${s.subject_id}, '${s.subject_name.replace(/'/g, "\\'")}')"
                style="background-color: #fee2e2; color: #dc2626; border: 1px solid #fecaca; padding: 4px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;"
                onmouseover="this.style.backgroundColor='#fecaca'"
                onmouseout="this.style.backgroundColor='#fee2e2'">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 12H6"/></svg>
                ${@json(__('Drop'))}
               </button>`;

        const bgColor = i % 2 === 0 ? '' : 'background-color: #f9fafb;';
        tbody.innerHTML += `
            <tr style="${bgColor}">
                <td class="px-4 py-3 text-gray-500">${i + 1}</td>
                <td class="px-4 py-3 font-mono text-xs text-gray-600">${s.subject_code}</td>
                <td class="px-4 py-3 font-medium text-gray-800">${s.subject_name}</td>
                <td class="px-4 py-3 text-center font-semibold">${parseFloat(s.coefficient).toFixed(1)}</td>
                <td class="px-4 py-3 text-center">${typeBadge}</td>
                <td class="px-4 py-3 text-center">${actionHtml}</td>
            </tr>`;
    });
}

// =========================================================================
// Render available electives
// =========================================================================
function renderAvailableElectives(electives) {
    const section = document.getElementById('electives_section');
    const tbody = document.getElementById('electives_tbody');
    const subtitle = document.getElementById('electives_subtitle');

    if (electives.length === 0) {
        section.classList.add('hidden');
        return;
    }

    section.classList.remove('hidden');
    subtitle.textContent = electives.length + ' ' + @json(__('elective subjects available to add'));
    tbody.innerHTML = '';

    electives.forEach((s, i) => {
        const bgColor = i % 2 === 0 ? '' : 'background-color: #f9fafb;';
        tbody.innerHTML += `
            <tr style="${bgColor}">
                <td class="px-4 py-3 text-gray-500">${i + 1}</td>
                <td class="px-4 py-3 font-mono text-xs text-gray-600">${s.subject_code}</td>
                <td class="px-4 py-3 font-medium text-gray-800">${s.subject_name}</td>
                <td class="px-4 py-3 text-center font-semibold">${parseFloat(s.coefficient).toFixed(1)}</td>
                <td class="px-4 py-3 text-center">
                    <button onclick="addSubject(${s.subject_id}, '${s.subject_name.replace(/'/g, "\\'")}')"
                        style="background-color: #d1fae5; color: #059669; border: 1px solid #a7f3d0; padding: 4px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;"
                        onmouseover="this.style.backgroundColor='#a7f3d0'"
                        onmouseout="this.style.backgroundColor='#d1fae5'">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/></svg>
                        ${@json(__('Add'))}
                    </button>
                </td>
            </tr>`;
    });
}

// =========================================================================
// Add subject
// =========================================================================
async function addSubject(subjectId, subjectName) {
    if (!currentEnrollmentId) return;

    if (!confirm(@json(__('Add subject')) + ` "${subjectName}" ?`)) return;

    hideAlerts();

    try {
        const res = await fetch(`${baseUrl}/add`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ enrollment_id: currentEnrollmentId, subject_id: subjectId })
        });
        const data = await res.json();

        if (!res.ok) {
            showError(data.error || @json(__('Failed to add subject.')));
            return;
        }

        showSuccess(data.message || @json(__('Subject added successfully.')));
        searchStudent(); // Refresh the data
    } catch (e) {
        showError(@json(__('Network error. Please try again.')));
        console.error(e);
    }
}

// =========================================================================
// Drop subject
// =========================================================================
async function dropSubject(subjectId, subjectName) {
    if (!currentEnrollmentId) return;

    if (!confirm(@json(__('Drop subject')) + ` "${subjectName}" ?\n\n` + @json(__('This action cannot be undone if marks have been entered.')))) return;

    hideAlerts();

    try {
        const res = await fetch(`${baseUrl}/drop`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ enrollment_id: currentEnrollmentId, subject_id: subjectId })
        });
        const data = await res.json();

        if (!res.ok) {
            showError(data.error || @json(__('Failed to drop subject.')));
            return;
        }

        showSuccess(data.message || @json(__('Subject dropped successfully.')));
        searchStudent(); // Refresh the data
    } catch (e) {
        showError(@json(__('Network error. Please try again.')));
        console.error(e);
    }
}

// =========================================================================
// Alert helpers
// =========================================================================
function showError(msg) {
    document.getElementById('error_text').textContent = msg;
    document.getElementById('error_alert').classList.remove('hidden');
    document.getElementById('success_alert').classList.add('hidden');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function showSuccess(msg) {
    document.getElementById('success_text').textContent = msg;
    document.getElementById('success_alert').classList.remove('hidden');
    document.getElementById('error_alert').classList.add('hidden');
    setTimeout(() => document.getElementById('success_alert').classList.add('hidden'), 4000);
}

function hideAlerts() {
    document.getElementById('error_alert').classList.add('hidden');
    document.getElementById('success_alert').classList.add('hidden');
}
</script>
@endpush
