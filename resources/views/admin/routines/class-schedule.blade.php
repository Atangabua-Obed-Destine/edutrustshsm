@extends('layouts.admin')

@section('title', __('Add / Edit Class Schedule'))

@section('content')
<div style="max-width: 1200px;">

    {{-- Header --}}
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 700; color: #1e293b;">{{ __('Add / Edit Class Schedule') }}</h2>
            <p style="font-size: 0.825rem; color: #64748b; margin-top: 2px;">{{ __('Set up weekly timetable for each class section') }}</p>
        </div>
        <a href="{{ route('admin.timetable.teacher-schedule') }}"
           style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; font-size: 0.8rem; color: #475569; border: 1px solid #cbd5e1; border-radius: 8px; text-decoration: none; background: #fff;"
           onmouseover="this.style.backgroundColor='#f1f5f9'" onmouseout="this.style.backgroundColor='#fff'">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            {{ __('Teacher Routines') }}
        </a>
    </div>

    {{-- Success Alert --}}
    @if(session('success'))
    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <svg width="18" height="18" style="color: #22c55e; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span style="font-size: 0.85rem; color: #166534; font-weight: 500;">{{ session('success') }}</span>
        </div>
    </div>
    @endif

    {{-- Error Alert --}}
    @if(session('error'))
    <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <svg width="18" height="18" style="color: #ef4444; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span style="font-size: 0.85rem; color: #b91c1c; font-weight: 500;">{{ session('error') }}</span>
        </div>
    </div>
    @endif

    @if($errors->any())
    <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px;">
        <ul style="list-style: disc; padding-left: 18px; font-size: 0.85rem; color: #b91c1c;">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Filter Bar --}}
    <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; padding: 20px; margin-bottom: 20px;">
        <form method="GET" action="{{ route('admin.timetable.class-schedule') }}" id="filterForm">
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; align-items: end;">
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Session') }} <span style="color: #ef4444;">*</span></label>
                    <select name="academic_session_id" id="sessionSelect" required
                            style="width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; background: #fff; appearance: auto; -webkit-appearance: menulist;">
                        <option value="">{{ __('— Select Session —') }}</option>
                        @foreach($sessions as $session)
                            <option value="{{ $session->id }}" {{ $sessionId == $session->id ? 'selected' : '' }}>{{ $session->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Form') }} <span style="color: #ef4444;">*</span></label>
                    <select name="form_id" id="formSelect" required
                            style="width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; background: #fff; appearance: auto; -webkit-appearance: menulist;">
                        <option value="">{{ __('— Select Form —') }}</option>
                        @foreach($forms as $form)
                            <option value="{{ $form->id }}" {{ $formId == $form->id ? 'selected' : '' }}>{{ $form->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Section') }} <span style="color: #ef4444;">*</span></label>
                    <select name="class_section_id" id="sectionSelect" required
                            style="width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; background: #fff; appearance: auto; -webkit-appearance: menulist;">
                        <option value="">{{ __('— Select Section —') }}</option>
                        @foreach($classSections as $cs)
                            <option value="{{ $cs->id }}" {{ $classSectionId == $cs->id ? 'selected' : '' }}>{{ $cs->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button type="submit" id="loadBtn"
                            style="width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 20px; background: #0ea5e9; color: #fff; border: none; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer;"
                            onmouseover="this.style.backgroundColor='#0284c7'" onmouseout="this.style.backgroundColor='#0ea5e9'">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        {{ __('Load') }}
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Schedule Builder --}}
    @if($filtered && $selectedSection)
    <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; overflow: hidden;">
        {{-- Section info bar --}}
        <div style="padding: 16px 24px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h3 style="font-size: 1.05rem; font-weight: 600; color: #1e293b;">{{ $selectedSection->name }}</h3>
                <p style="font-size: 0.8rem; color: #64748b;">{{ $selectedSection->form->name ?? '' }} &middot; {{ $sessions->firstWhere('id', $sessionId)->name ?? '' }}</p>
            </div>
            <div style="text-align: right;">
                @php
                    $totalEntries = 0;
                    foreach ($entries as $dayEntries) { $totalEntries += $dayEntries->count(); }
                @endphp
                <p style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">{{ $totalEntries }}</p>
                <p style="font-size: 0.7rem; color: #64748b;">{{ __('total periods assigned') }}</p>
            </div>
        </div>

        {{-- Day Tabs --}}
        <div style="border-bottom: 1px solid #e2e8f0; background: #fff; overflow-x: auto;">
            <div style="display: flex;">
                @foreach($days as $day)
                <button type="button" onclick="switchDay('{{ $day }}')"
                        id="tab-{{ $day }}"
                        style="padding: 12px 24px; font-size: 0.82rem; font-weight: 600; white-space: nowrap; border: none; background: transparent; cursor: pointer; border-bottom: 2px solid {{ $day === $activeDay ? '#0ea5e9' : 'transparent' }}; color: {{ $day === $activeDay ? '#0ea5e9' : '#64748b' }}; {{ $day === $activeDay ? 'background: rgba(14,165,233,0.05);' : '' }} transition: all .15s;"
                        onmouseover="if(!this.classList.contains('active-tab')){this.style.color='#334155'; this.style.borderBottomColor='#cbd5e1';}"
                        onmouseout="if(!this.classList.contains('active-tab')){this.style.color='#64748b'; this.style.borderBottomColor='transparent';}">
                    {{ strtoupper($day) }}
                    @if($filtered)
                        @php $dayCount = isset($entries[$day]) ? $entries[$day]->count() : 0; @endphp
                        @if($dayCount > 0)
                        <span style="margin-left: 6px; display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; font-size: 0.65rem; font-weight: 700; border-radius: 50%; background: {{ $day === $activeDay ? '#0ea5e9' : '#e2e8f0' }}; color: {{ $day === $activeDay ? '#fff' : '#475569' }};">{{ $dayCount }}</span>
                        @endif
                    @endif
                </button>
                @endforeach
            </div>
        </div>

        {{-- Day Panels --}}
        @foreach($days as $day)
        <div id="panel-{{ $day }}" class="day-panel" style="padding: 24px; {{ $day !== $activeDay ? 'display:none;' : '' }}">
            <form method="POST" action="{{ route('admin.timetable.save-day-schedule') }}" id="form-{{ $day }}" onsubmit="return validateBeforeSave()">
                @csrf
                <input type="hidden" name="class_section_id" id="hiddenSection-{{ $day }}" value="{{ $classSectionId }}">
                <input type="hidden" name="academic_session_id" id="hiddenSession-{{ $day }}" value="{{ $sessionId }}">
                <input type="hidden" name="form_id" id="hiddenForm-{{ $day }}" value="{{ $formId }}">
                <input type="hidden" name="day_of_week" value="{{ $day }}">

                {{-- Column Headers --}}
                <div style="display: grid; grid-template-columns: 3fr 2fr 2fr 1.5fr 1.5fr 40px; gap: 10px; margin-bottom: 10px; padding: 0 4px;">
                    <div style="font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Subject') }} *</div>
                    <div style="font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Teacher') }} *</div>
                    <div style="font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Room') }}</div>
                    <div style="font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('From') }} *</div>
                    <div style="font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('To') }} *</div>
                    <div></div>
                </div>

                {{-- Entry Rows Container --}}
                <div id="entries-{{ $day }}" style="display: flex; flex-direction: column; gap: 8px;">
                    @php $dayEntries = $entries[$day] ?? collect(); @endphp
                    @forelse($dayEntries as $idx => $entry)
                    @include('admin.routines._schedule-row', ['idx' => $idx, 'entry' => $entry, 'subjects' => $subjects, 'teachers' => $teachers, 'rooms' => $rooms])
                    @empty
                    @include('admin.routines._schedule-row', ['idx' => 0, 'entry' => null, 'subjects' => $subjects, 'teachers' => $teachers, 'rooms' => $rooms])
                    @endforelse
                </div>

                {{-- Action Buttons --}}
                <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 20px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                    <button type="button" onclick="addRow('{{ $day }}')"
                            style="display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; background: #22c55e; color: #fff; border: none; border-radius: 8px; font-size: 0.82rem; font-weight: 600; cursor: pointer;"
                            onmouseover="this.style.backgroundColor='#16a34a'" onmouseout="this.style.backgroundColor='#22c55e'">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        {{ __('Add Period') }}
                    </button>
                    <button type="submit"
                            style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 24px; background: #1e293b; color: #fff; border: none; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer;"
                            onmouseover="this.style.backgroundColor='#334155'" onmouseout="this.style.backgroundColor='#1e293b'">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        {{ __('Save :day Schedule', ['day' => ucfirst($day)]) }}
                    </button>
                </div>
            </form>
        </div>
        @endforeach
    </div>
    @else
    {{-- Prompt to select filters --}}
    <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; padding: 48px 24px; text-align: center;">
        <svg style="width: 56px; height: 56px; margin: 0 auto 16px; color: #cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        <h3 style="font-size: 1.05rem; font-weight: 600; color: #475569; margin-bottom: 6px;">{{ __('Select a Class to Begin') }}</h3>
        <p style="font-size: 0.85rem; color: #94a3b8;">{{ __('Choose a Session, Form, and Section above, then click Load to view or edit the timetable.') }}</p>
    </div>
    @endif
</div>

@push('scripts')
<script>
    // Options JSON for dynamic rows
    const subjectOptions = @json($subjects->map(fn($s) => ['id' => $s->id, 'label' => $s->name . ' (' . $s->code . ')']));
    const teacherOptions = @json($teachers->map(fn($t) => ['id' => $t->id, 'label' => $t->full_name]));
    const roomOptions = @json($rooms->map(fn($r) => ['id' => $r->id, 'label' => $r->name]));

    function getNextIndex(day) {
        const container = document.getElementById('entries-' + day);
        const rows = container.querySelectorAll('.entry-row');
        let max = -1;
        rows.forEach(row => {
            const inputs = row.querySelectorAll('select, input');
            inputs.forEach(el => {
                const match = el.name.match(/entries\[(\d+)\]/);
                if (match) max = Math.max(max, parseInt(match[1]));
            });
        });
        return max + 1;
    }

    const inputStyle = 'width:100%; padding:8px 10px; border:1px solid #d1d5db; border-radius:8px; outline:none; background:#fff; font-size:0.82rem; appearance:auto; -webkit-appearance:menulist;';
    const timeStyle  = 'width:100%; padding:8px 10px; border:1px solid #d1d5db; border-radius:8px; outline:none; background:#fff; font-size:0.82rem;';

    function buildSelect(name, options, required, placeholder) {
        let html = '<select name="' + name + '"' + (required ? ' required' : '') +
            ' style="' + inputStyle + '"' +
            ' onfocus="this.style.borderColor=\'#3b82f6\'; this.style.boxShadow=\'0 0 0 2px rgba(59,130,246,.15)\';"' +
            ' onblur="this.style.borderColor=\'#d1d5db\'; this.style.boxShadow=\'none\';">' +
            '<option value="">' + (placeholder || '{{ __("Select") }}') + '</option>';
        options.forEach(function(opt) {
            html += '<option value="' + opt.id + '">' + escapeHtml(opt.label) + '</option>';
        });
        html += '</select>';
        return html;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function addRow(day) {
        const container = document.getElementById('entries-' + day);
        const emptyState = container.querySelector('.empty-state');
        if (emptyState) emptyState.remove();

        const idx = getNextIndex(day);
        const row = document.createElement('div');
        row.className = 'entry-row animate-fade-in';
        row.style.cssText = 'display:grid; grid-template-columns:3fr 2fr 2fr 1.5fr 1.5fr 40px; gap:10px; align-items:center; padding:10px 12px; background:#eff6ff; border-radius:10px; border:1px solid #bfdbfe;';
        row.innerHTML =
            '<div>' + buildSelect('entries[' + idx + '][subject_id]', subjectOptions, true, '{{ __("Select") }}') + '</div>' +
            '<div>' + buildSelect('entries[' + idx + '][teacher_id]', teacherOptions, true, '{{ __("Select") }}') + '</div>' +
            '<div>' + buildSelect('entries[' + idx + '][room_id]', roomOptions, false, '{{ __("Select") }}') + '</div>' +
            '<div><input type="time" name="entries[' + idx + '][start_time]" required style="' + timeStyle + '" onfocus="this.style.borderColor=\'#3b82f6\'; this.style.boxShadow=\'0 0 0 2px rgba(59,130,246,.15)\';" onblur="this.style.borderColor=\'#d1d5db\'; this.style.boxShadow=\'none\';"></div>' +
            '<div><input type="time" name="entries[' + idx + '][end_time]" required style="' + timeStyle + '" onfocus="this.style.borderColor=\'#3b82f6\'; this.style.boxShadow=\'0 0 0 2px rgba(59,130,246,.15)\';" onblur="this.style.borderColor=\'#d1d5db\'; this.style.boxShadow=\'none\';"></div>' +
            '<div style="display:flex; justify-content:center;">' +
                '<button type="button" onclick="removeRow(this)" title="{{ __("Remove") }}" style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border:1px solid #fca5a5; color:#dc2626; background:transparent; border-radius:8px; cursor:pointer;" onmouseover="this.style.background=\'#fef2f2\'" onmouseout="this.style.background=\'transparent\'">' +
                    '<svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>' +
                '</button>' +
            '</div>';
        container.appendChild(row);
    }

    function removeRow(btn) {
        const row = btn.closest('.entry-row');
        const container = row.parentElement;
        row.remove();
        if (container.querySelectorAll('.entry-row').length === 0) {
            const dayName = container.id.replace('entries-', '');
            container.innerHTML =
                '<div class="empty-state" style="text-align:center; padding:32px 0; color:#9ca3af;">' +
                    '<svg style="width:48px; height:48px; margin:0 auto 12px; color:#d1d5db;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>' +
                    '<p style="font-size:0.85rem;">{{ __("No entries for") }} ' + dayName.charAt(0).toUpperCase() + dayName.slice(1) + ' {{ __("yet. Click + Add Period to get started.") }}</p>' +
                '</div>';
        }
    }

    // Day tab switching
    function switchDay(day) {
        document.querySelectorAll('[id^="tab-"]').forEach(tab => {
            if (!tab.id.startsWith('tab-')) return;
            tab.style.color = '#64748b';
            tab.style.borderBottomColor = 'transparent';
            tab.style.background = 'transparent';
            tab.classList.remove('active-tab');
            const badge = tab.querySelector('span');
            if (badge) { badge.style.background = '#e2e8f0'; badge.style.color = '#475569'; }
        });
        document.querySelectorAll('.day-panel').forEach(panel => {
            panel.style.display = 'none';
        });

        const activeTab = document.getElementById('tab-' + day);
        activeTab.style.color = '#0ea5e9';
        activeTab.style.borderBottomColor = '#0ea5e9';
        activeTab.style.background = 'rgba(14,165,233,0.05)';
        activeTab.classList.add('active-tab');
        const activeBadge = activeTab.querySelector('span');
        if (activeBadge) { activeBadge.style.background = '#0ea5e9'; activeBadge.style.color = '#fff'; }

        document.getElementById('panel-' + day).style.display = '';
    }

    // Cascading: Form → Section
    document.getElementById('formSelect').addEventListener('change', function() {
        const formId = this.value;
        const sectionSelect = document.getElementById('sectionSelect');
        sectionSelect.innerHTML = '<option value="">{{ __("\u2014 Loading... \u2014") }}</option>';

        if (!formId) {
            sectionSelect.innerHTML = '<option value="">{{ __("\u2014 Select Section \u2014") }}</option>';
            return;
        }

        fetch('{{ url("admin/timetable/sections-by-form") }}/' + formId)
            .then(r => r.json())
            .then(sections => {
                let html = '<option value="">{{ __("\u2014 Select Section \u2014") }}</option>';
                sections.forEach(s => {
                    html += '<option value="' + s.id + '">' + escapeHtml(s.name) + '</option>';
                });
                sectionSelect.innerHTML = html;
            })
            .catch(() => {
                sectionSelect.innerHTML = '<option value="">{{ __("\u2014 Select Section \u2014") }}</option>';
            });
    });

    // Validate filter is filled before allowing save
    function validateBeforeSave() {
        const sessionVal = document.getElementById('sessionSelect').value;
        const formVal = document.getElementById('formSelect').value;
        const sectionVal = document.getElementById('sectionSelect').value;

        if (!sessionVal || !formVal || !sectionVal) {
            alert('{{ __("Please select Session, Form, and Section using the filter above before saving.") }}');
            return false;
        }

        // Sync hidden fields from filter dropdowns to all day forms
        document.querySelectorAll('[id^="hiddenSession-"]').forEach(el => el.value = sessionVal);
        document.querySelectorAll('[id^="hiddenForm-"]').forEach(el => el.value = formVal);
        document.querySelectorAll('[id^="hiddenSection-"]').forEach(el => el.value = sectionVal);

        return true;
    }
</script>
<style>
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in { animation: fadeIn 0.2s ease-out; }
</style>
@endpush
@endsection
