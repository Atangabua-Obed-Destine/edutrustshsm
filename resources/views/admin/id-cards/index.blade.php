@extends('layouts.admin')
@section('title', __('Student ID Cards'))

@section('content')
<div style="max-width: 1200px; margin: 0 auto;">
    {{-- Header --}}
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 700; color: #1e293b;">{{ __('Student ID Cards') }}</h1>
            <p style="font-size: 14px; color: #64748b; margin-top: 4px;">{{ __('Select students to generate and print ID cards') }}</p>
        </div>
        @if($enrollments->count())
        <button type="button" id="printSelectedBtn" onclick="submitPrint()"
                style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #1e293b; color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer;"
                onmouseover="this.style.background='#334155'" onmouseout="this.style.background='#1e293b'">
            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            {{ __('Print Selected') }} (<span id="selectedCount">0</span>)
        </button>
        @endif
    </div>

    {{-- Filters --}}
    <div style="background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px; margin-bottom: 24px;">
        <form method="GET" action="{{ route('admin.admissions.id-cards.index') }}" id="filterForm">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; align-items: end;">
                {{-- Session --}}
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 4px;">{{ __('Session') }}</label>
                    <select name="academic_session_id" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; background: white;">
                        @foreach($sessions as $session)
                            <option value="{{ $session->id }}" {{ $sessionId == $session->id ? 'selected' : '' }}>{{ $session->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Form --}}
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 4px;">{{ __('Form') }}</label>
                    <select name="form_id" id="formFilter" onchange="loadSections(this.value)"
                            style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; background: white;">
                        <option value="">{{ __('— Select Form —') }}</option>
                        @foreach($forms as $form)
                            <option value="{{ $form->id }}" {{ $formId == $form->id ? 'selected' : '' }}>{{ $form->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Class Section --}}
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 4px;">{{ __('Class Section') }}</label>
                    <select name="class_section_id" id="sectionFilter"
                            style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; background: white;">
                        <option value="">{{ __('— All Sections —') }}</option>
                        @foreach($classSections as $cs)
                            <option value="{{ $cs->id }}" {{ $classSectionId == $cs->id ? 'selected' : '' }}>{{ $cs->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Search --}}
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 4px;">{{ __('Search') }}</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('Name or ID...') }}"
                           style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
                </div>

                {{-- Filter Button --}}
                <div>
                    <button type="submit"
                            style="width: 100%; padding: 9px 16px; background: #2563eb; color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer;"
                            onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
                        {{ __('Filter') }}
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Results --}}
    @if($enrollments->count())
    <div style="background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden;">
        {{-- Table --}}
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                    <th style="padding: 12px 16px; text-align: left; width: 40px;">
                        <input type="checkbox" id="selectAll" onchange="toggleAll(this.checked)"
                               style="width: 16px; height: 16px; accent-color: #2563eb; cursor: pointer;">
                    </th>
                    <th style="padding: 12px 8px; text-align: left; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; width: 56px;">{{ __('Photo') }}</th>
                    <th style="padding: 12px 8px; text-align: left; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('Student ID') }}</th>
                    <th style="padding: 12px 8px; text-align: left; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('Name') }}</th>
                    <th style="padding: 12px 8px; text-align: left; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('Class') }}</th>
                    <th style="padding: 12px 16px; text-align: center; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; width: 140px;">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($enrollments as $enrollment)
                <tr style="border-bottom: 1px solid #f1f5f9; cursor: pointer;"
                    class="student-row"
                    onclick="toggleRow(this)"
                    onmouseover="this.style.background='#f8fafc';"
                    onmouseout="if(!this.classList.contains('selected')){this.style.background='white';}else{this.style.background='#eff6ff';}">
                    {{-- Checkbox --}}
                    <td style="padding: 10px 16px;">
                        <input type="checkbox" class="student-checkbox" value="{{ $enrollment->id }}"
                               style="width: 16px; height: 16px; accent-color: #2563eb; cursor: pointer;"
                               onclick="event.stopPropagation(); updateSelected();">
                    </td>
                    {{-- Photo (clickable to change) --}}
                    <td style="padding: 10px 8px;" onclick="event.stopPropagation();">
                        <div id="photo-{{ $enrollment->student->id }}" style="width: 40px; height: 40px; border-radius: 50%; overflow: hidden; background: #e2e8f0; cursor: pointer; position: relative;"
                             onclick="openPhotoModal({{ $enrollment->student->id }}, '{{ $enrollment->student->last_name }} {{ $enrollment->student->first_name }}', '{{ $enrollment->student->photo ? asset('storage/' . $enrollment->student->photo) : '' }}')"
                             title="{{ __('Click to change photo') }}">
                            @if($enrollment->student->photo)
                                <img src="{{ asset('storage/' . $enrollment->student->photo) }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                            @else
                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #cbd5e1; color: #475569; font-weight: 700; font-size: 13px;">
                                    {{ strtoupper(substr($enrollment->student->first_name, 0, 1) . substr($enrollment->student->last_name, 0, 1)) }}
                                </div>
                            @endif
                            <div style="position: absolute; bottom: -2px; right: -2px; width: 16px; height: 16px; background: #2563eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid white;">
                                <svg style="width: 8px; height: 8px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                        </div>
                    </td>
                    {{-- Student ID --}}
                    <td style="padding: 10px 8px; font-size: 13px; font-weight: 500; color: #1e293b; font-family: monospace;">
                        {{ $enrollment->student->student_id }}
                    </td>
                    {{-- Name --}}
                    <td style="padding: 10px 8px;">
                        <div style="font-size: 14px; font-weight: 600; color: #1e293b;">{{ $enrollment->student->last_name }} {{ $enrollment->student->first_name }}</div>
                        @if(!$enrollment->student->photo)
                        <span style="font-size: 10px; background: #fef3c7; color: #92400e; padding: 1px 5px; border-radius: 3px;">{{ __('No photo') }}</span>
                        @endif
                    </td>
                    {{-- Class --}}
                    <td style="padding: 10px 8px; font-size: 13px; color: #374151;">
                        {{ $enrollment->classSection->name ?? '—' }}{{ $enrollment->stream ? ' — ' . $enrollment->stream->name : '' }}
                    </td>
                    {{-- Actions --}}
                    <td style="padding: 10px 16px; text-align: center;" onclick="event.stopPropagation();">
                        <div style="display: inline-flex; gap: 6px;">
                            <button type="button" onclick="printSingle({{ $enrollment->id }})" title="{{ __('Print') }}"
                                    style="padding: 6px 10px; background: #1e293b; color: white; border: none; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;"
                                    onmouseover="this.style.background='#334155'" onmouseout="this.style.background='#1e293b'">
                                <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                {{ __('Print') }}
                            </button>
                            <button type="button" onclick="downloadSingle({{ $enrollment->id }})" title="{{ __('Download') }}"
                                    style="padding: 6px 10px; background: #2563eb; color: white; border: none; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;"
                                    onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
                                <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                {{ __('Download') }}
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Footer bar --}}
        <div style="padding: 12px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
            <span style="font-size: 13px; color: #64748b;">{{ $enrollments->count() }} {{ __('student(s)') }} &bull; <span id="selectedCount">0</span> {{ __('selected') }}</span>
        </div>
    </div>

    {{-- Hidden print form --}}
    <form id="printForm" method="POST" action="{{ route('admin.admissions.id-cards.print') }}" target="_blank">
        @csrf
        <div id="printInputs"></div>
    </form>

    @elseif($formId || $classSectionId || $search)
    <div style="background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 60px 20px; text-align: center;">
        <svg style="width: 48px; height: 48px; color: #cbd5e1; margin: 0 auto 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        <p style="font-size: 16px; font-weight: 600; color: #64748b;">{{ __('No students found matching your filters.') }}</p>
    </div>
    @else
    <div style="background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 60px 20px; text-align: center;">
        <svg style="width: 48px; height: 48px; color: #cbd5e1; margin: 0 auto 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
        <p style="font-size: 16px; font-weight: 600; color: #64748b;">{{ __('Select a Form and click Filter to load students.') }}</p>
    </div>
    @endif
</div>

{{-- Photo Upload Modal --}}
<div id="photoModal" style="display: none; position: fixed; inset: 0; z-index: 1000; background: rgba(0,0,0,0.5); align-items: center; justify-content: center;"
     onclick="if(event.target===this) closePhotoModal();">
    <div style="background: white; border-radius: 16px; width: 400px; max-width: 90vw; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        {{-- Modal Header --}}
        <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
            <h3 style="font-size: 16px; font-weight: 700; color: #1e293b;">{{ __('Update Student Photo') }}</h3>
            <button onclick="closePhotoModal()" style="background: none; border: none; cursor: pointer; padding: 4px; color: #94a3b8;"
                    onmouseover="this.style.color='#1e293b'" onmouseout="this.style.color='#94a3b8'">
                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Modal Body --}}
        <div style="padding: 20px;">
            <p id="photoModalStudentName" style="font-size: 14px; font-weight: 600; color: #374151; text-align: center; margin-bottom: 16px;"></p>

            {{-- Current Photo Preview --}}
            <div style="text-align: center; margin-bottom: 16px;">
                <div id="photoPreviewContainer" style="width: 120px; height: 120px; border-radius: 50%; overflow: hidden; margin: 0 auto; border: 3px solid #e2e8f0; background: #f1f5f9;">
                    <img id="photoPreviewImg" src="" alt="" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                    <div id="photoPreviewPlaceholder" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 36px; font-weight: 800; color: #94a3b8;">
                        ?
                    </div>
                </div>
            </div>

            {{-- File Input --}}
            <div id="photoDropZone" style="border: 2px dashed #d1d5db; border-radius: 10px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.15s;"
                 onclick="document.getElementById('photoFileInput').click();"
                 onmouseover="this.style.borderColor='#2563eb'; this.style.background='#eff6ff';"
                 onmouseout="this.style.borderColor='#d1d5db'; this.style.background='white';">
                <svg style="width: 32px; height: 32px; color: #94a3b8; margin: 0 auto 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <p style="font-size: 13px; color: #64748b;">{{ __('Click to select photo') }}</p>
                <p style="font-size: 11px; color: #94a3b8; margin-top: 4px;">JPG, PNG, WEBP &bull; {{ __('Max 2MB') }}</p>
            </div>
            <input type="file" id="photoFileInput" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="previewPhoto(this);">

            {{-- Error message --}}
            <p id="photoError" style="display: none; font-size: 12px; color: #dc2626; margin-top: 8px; text-align: center;"></p>
        </div>

        {{-- Modal Footer --}}
        <div style="padding: 12px 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 8px; background: #f8fafc;">
            <button onclick="closePhotoModal()"
                    style="padding: 8px 16px; background: white; color: #374151; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer;"
                    onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                {{ __('Cancel') }}
            </button>
            <button id="photoSaveBtn" onclick="savePhoto()" disabled
                    style="padding: 8px 16px; background: #2563eb; color: white; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; opacity: 0.5;"
                    onmouseover="if(!this.disabled) this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
                {{ __('Save Photo') }}
            </button>
        </div>
    </div>
</div>

<script>
function loadSections(formId) {
    const sel = document.getElementById('sectionFilter');
    sel.innerHTML = '<option value="">{{ __("— All Sections —") }}</option>';
    if (!formId) return;
    fetch("{{ url('admin/admissions/id-cards/sections-by-form') }}/" + formId)
        .then(r => r.json())
        .then(data => {
            data.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.name;
                sel.appendChild(opt);
            });
        });
}

function toggleAll(checked) {
    document.querySelectorAll('.student-checkbox').forEach(cb => {
        cb.checked = checked;
        const row = cb.closest('.student-row');
        if (checked) {
            row.classList.add('selected');
            row.style.background = '#eff6ff';
        } else {
            row.classList.remove('selected');
            row.style.background = 'white';
        }
    });
    updateSelected();
}

function toggleRow(row) {
    const cb = row.querySelector('.student-checkbox');
    cb.checked = !cb.checked;
    if (cb.checked) {
        row.classList.add('selected');
        row.style.background = '#eff6ff';
    } else {
        row.classList.remove('selected');
        row.style.background = 'white';
    }
    updateSelected();
}

function updateSelected() {
    const count = document.querySelectorAll('.student-checkbox:checked').length;
    const el = document.getElementById('selectedCount');
    if (el) el.textContent = count;

    const btn = document.getElementById('printSelectedBtn');
    if (btn) {
        btn.style.opacity = count > 0 ? '1' : '0.5';
        btn.style.pointerEvents = count > 0 ? 'auto' : 'none';
    }

    // Update select-all checkbox
    const total = document.querySelectorAll('.student-checkbox').length;
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.checked = count === total && total > 0;
        selectAll.indeterminate = count > 0 && count < total;
    }
}

function submitPrint() {
    const checked = document.querySelectorAll('.student-checkbox:checked');
    if (checked.length === 0) {
        alert('{{ __("Please select at least one student.") }}');
        return;
    }
    const container = document.getElementById('printInputs');
    container.innerHTML = '';
    checked.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'enrollment_ids[]';
        input.value = cb.value;
        container.appendChild(input);
    });
    document.getElementById('printForm').submit();
}

// Init
document.addEventListener('DOMContentLoaded', () => updateSelected());

function printSingle(enrollmentId) {
    const container = document.getElementById('printInputs');
    container.innerHTML = '';
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'enrollment_ids[]';
    input.value = enrollmentId;
    container.appendChild(input);
    document.getElementById('printForm').submit();
}

function downloadSingle(enrollmentId) {
    // Open in new window; user can Save As / Print to PDF
    const container = document.getElementById('printInputs');
    container.innerHTML = '';
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'enrollment_ids[]';
    input.value = enrollmentId;
    container.appendChild(input);
    const form = document.getElementById('printForm');
    form.submit();
}

// ── Photo Modal ──
let currentStudentId = null;

function openPhotoModal(studentId, studentName, currentPhotoUrl) {
    currentStudentId = studentId;
    document.getElementById('photoModalStudentName').textContent = studentName;
    document.getElementById('photoFileInput').value = '';
    document.getElementById('photoError').style.display = 'none';

    const img = document.getElementById('photoPreviewImg');
    const placeholder = document.getElementById('photoPreviewPlaceholder');
    if (currentPhotoUrl) {
        img.src = currentPhotoUrl;
        img.style.display = 'block';
        placeholder.style.display = 'none';
    } else {
        img.style.display = 'none';
        placeholder.style.display = 'flex';
    }

    const saveBtn = document.getElementById('photoSaveBtn');
    saveBtn.disabled = true;
    saveBtn.style.opacity = '0.5';

    const modal = document.getElementById('photoModal');
    modal.style.display = 'flex';
}

function closePhotoModal() {
    document.getElementById('photoModal').style.display = 'none';
    currentStudentId = null;
}

function previewPhoto(input) {
    const errEl = document.getElementById('photoError');
    errEl.style.display = 'none';

    if (!input.files || !input.files[0]) return;
    const file = input.files[0];

    // Validate
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        errEl.textContent = '{{ __("Please select a JPG, PNG or WEBP image.") }}';
        errEl.style.display = 'block';
        input.value = '';
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        errEl.textContent = '{{ __("File is too large. Maximum size is 2MB.") }}';
        errEl.style.display = 'block';
        input.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const img = document.getElementById('photoPreviewImg');
        img.src = e.target.result;
        img.style.display = 'block';
        document.getElementById('photoPreviewPlaceholder').style.display = 'none';

        const saveBtn = document.getElementById('photoSaveBtn');
        saveBtn.disabled = false;
        saveBtn.style.opacity = '1';
    };
    reader.readAsDataURL(file);
}

function savePhoto() {
    const fileInput = document.getElementById('photoFileInput');
    if (!fileInput.files || !fileInput.files[0] || !currentStudentId) return;

    const saveBtn = document.getElementById('photoSaveBtn');
    saveBtn.disabled = true;
    saveBtn.textContent = '{{ __("Saving...") }}';
    saveBtn.style.opacity = '0.6';

    const formData = new FormData();
    formData.append('photo', fileInput.files[0]);
    formData.append('_token', '{{ csrf_token() }}');

    fetch("{{ url('admin/admissions/id-cards/update-photo') }}/" + currentStudentId, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Update the photo in the table row
            const photoCell = document.getElementById('photo-' + currentStudentId);
            if (photoCell) {
                const existingImg = photoCell.querySelector('img');
                if (existingImg) {
                    existingImg.src = data.photo_url;
                } else {
                    // Replace placeholder with image
                    photoCell.innerHTML = '<img src="' + data.photo_url + '" alt="" style="width: 100%; height: 100%; object-fit: cover;">' +
                        photoCell.querySelector('div[style*="position: absolute"]').outerHTML;
                }
                // Remove "No photo" badge in the Name column
                const row = photoCell.closest('tr');
                if (row) {
                    const badge = row.querySelector('span[style*="fef3c7"]');
                    if (badge) badge.remove();
                }
            }
            closePhotoModal();
        } else {
            const errEl = document.getElementById('photoError');
            errEl.textContent = data.message || '{{ __("Failed to update photo.") }}';
            errEl.style.display = 'block';
        }
    })
    .catch(() => {
        const errEl = document.getElementById('photoError');
        errEl.textContent = '{{ __("An error occurred. Please try again.") }}';
        errEl.style.display = 'block';
    })
    .finally(() => {
        saveBtn.disabled = false;
        saveBtn.textContent = '{{ __("Save Photo") }}';
        saveBtn.style.opacity = '1';
    });
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePhotoModal();
});
</script>
@endsection
