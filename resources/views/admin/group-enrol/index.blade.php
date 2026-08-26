@extends('layouts.admin')

@section('title', __('Group Enrol'))

@section('content')
<div style="display: flex; flex-direction: column; gap: 24px;">

    {{-- Page Header --}}
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: #111827;">{{ __('Group Enrol') }}</h1>
        <p style="margin-top: 4px; font-size: 0.875rem; color: #6b7280;">{{ __('Re-enrol multiple students at once into a new session, form, or class') }}</p>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px 16px; display: flex; align-items: center; gap: 10px;">
        <svg style="width: 20px; height: 20px; color: #22c55e; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span style="color: #166534; font-size: 0.875rem; font-weight: 500;">{{ session('success') }}</span>
    </div>
    @endif
    @if(session('error'))
    <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 16px; display: flex; align-items: center; gap: 10px;">
        <svg style="width: 20px; height: 20px; color: #ef4444; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span style="color: #991b1b; font-size: 0.875rem; font-weight: 500;">{{ session('error') }}</span>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- SOURCE: Load current students                                      --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e5e7eb; overflow: hidden;">
        <div style="padding: 16px 24px; background: #2563eb; border-bottom: 1px solid #1d4ed8; display: flex; align-items: center; gap: 10px;">
            <svg style="width: 20px; height: 20px; color: #fff; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <span style="font-size: 1rem; font-weight: 600; color: #fff;">{{ __('Current Enrolment — Select Students') }}</span>
        </div>

        <div style="padding: 24px;">
            <form method="GET" action="{{ route('admin.group-enrol.index') }}" id="sourceForm">
                <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; align-items: end;">
                    {{-- Academic Session --}}
                    <div>
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 4px;">{{ __('Academic Session') }} <span style="color: #ef4444;">*</span></label>
                        <select name="academic_session_id" id="srcSession"
                            style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; color: #111827; background: #fff; appearance: auto;">
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach($sessions as $s)
                                <option value="{{ $s->id }}" {{ $sessionId == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Form --}}
                    <div>
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 4px;">{{ __('Form') }} <span style="color: #ef4444;">*</span></label>
                        <select name="form_id" id="srcForm"
                            style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; color: #111827; background: #fff; appearance: auto;">
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach($forms as $f)
                                <option value="{{ $f->id }}" {{ $formId == $f->id ? 'selected' : '' }}>{{ $f->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Section --}}
                    <div>
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 4px;">{{ __('Section') }} <span style="color: #ef4444;">*</span></label>
                        <select name="class_section_id" id="srcSection"
                            style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; color: #111827; background: #fff; appearance: auto;">
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach($classSections as $cs)
                                <option value="{{ $cs->id }}" {{ $classSectionId == $cs->id ? 'selected' : '' }}>{{ $cs->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Term --}}
                    <div>
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 4px;">{{ __('Term') }} <span style="color: #ef4444;">*</span></label>
                        <select name="term_id" id="srcTerm"
                            style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; color: #111827; background: #fff; appearance: auto;">
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach($terms as $t)
                                <option value="{{ $t->id }}" {{ $termId == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Load Button --}}
                    <div>
                        <button type="submit"
                            style="width: 100%; padding: 8px 16px; background: #2563eb; color: #fff; border: none; border-radius: 8px; font-size: 0.875rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;"
                            onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
                            <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            {{ __('Load') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Students Table --}}
        @if($filtered)
        <div style="border-top: 1px solid #e5e7eb;">
            @if($enrollments->isEmpty())
            <div style="padding: 40px; text-align: center;">
                <svg style="width: 40px; height: 40px; margin: 0 auto 12px; color: #d1d5db;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <p style="font-size: 0.875rem; color: #6b7280;">{{ __('No students found for these filters.') }}</p>
            </div>
            @else
            <div style="padding: 12px 24px; background: #f0f9ff; border-bottom: 1px solid #e0f2fe; display: flex; align-items: center; justify-content: space-between;">
                <span style="font-size: 0.875rem; color: #0369a1; font-weight: 500;">
                    <span id="selectedCount">0</span> / {{ $enrollments->count() }} {{ __('student(s) selected') }}
                </span>
                <div style="display: flex; gap: 8px;">
                    <button type="button" onclick="toggleAllStudents(true)"
                        style="padding: 4px 12px; font-size: 0.75rem; font-weight: 600; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer;"
                        onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
                        {{ __('Select All') }}
                    </button>
                    <button type="button" onclick="toggleAllStudents(false)"
                        style="padding: 4px 12px; font-size: 0.75rem; font-weight: 600; background: #e5e7eb; color: #374151; border: none; border-radius: 6px; cursor: pointer;"
                        onmouseover="this.style.background='#d1d5db'" onmouseout="this.style.background='#e5e7eb'">
                        {{ __('Deselect All') }}
                    </button>
                </div>
            </div>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;" id="studentsTable">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 10px 16px; text-align: center; width: 40px;">
                                <input type="checkbox" id="selectAllCb" onchange="toggleAllStudents(this.checked)"
                                    style="width: 16px; height: 16px; accent-color: #2563eb; cursor: pointer;">
                            </th>
                            <th style="padding: 10px 16px; text-align: left; font-weight: 600; color: #374151;">{{ __('Student ID') }}</th>
                            <th style="padding: 10px 16px; text-align: left; font-weight: 600; color: #374151;">{{ __('Name') }}</th>
                            <th style="padding: 10px 16px; text-align: center; font-weight: 600; color: #374151;">{{ __('Sex') }}</th>
                            <th style="padding: 10px 16px; text-align: center; font-weight: 600; color: #374151;">{{ __('Stream') }}</th>
                            @foreach($sequences as $seq)
                            <th style="padding: 10px 16px; text-align: center; font-weight: 600; color: #374151;">{{ $seq->name }}</th>
                            @endforeach
                            <th style="padding: 10px 16px; text-align: center; font-weight: 600; color: #374151;">{{ __('Term Average') }}</th>
                            <th style="padding: 10px 16px; text-align: center; font-weight: 600; color: #374151;">{{ __('Rank') }}</th>
                            <th style="padding: 10px 16px; text-align: center; font-weight: 600; color: #374151;">{{ __('Batch') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($enrollments as $i => $enr)
                        @php
                            $avg = $enr->_term_average;
                            $isFail = $avg !== null && (float)$avg < 10;
                        @endphp
                        <tr style="border-bottom: 1px solid #f1f5f9; cursor: pointer;"
                            onmouseover="this.style.background='#f0f9ff'" onmouseout="this.style.background='{{ $i % 2 === 0 ? '#fff' : '#f8fafc' }}'"
                            onclick="toggleStudentRow(this, {{ $enr->student->id }})"
                            data-bg="{{ $i % 2 === 0 ? '#fff' : '#f8fafc' }}"
                            style="background: {{ $i % 2 === 0 ? '#fff' : '#f8fafc' }};">
                            <td style="padding: 10px 16px; text-align: center;">
                                <input type="checkbox" class="studentCb" value="{{ $enr->student->id }}"
                                    onchange="updateSelectedCount()" onclick="event.stopPropagation()"
                                    style="width: 16px; height: 16px; accent-color: #2563eb; cursor: pointer;">
                            </td>
                            <td style="padding: 10px 16px; font-weight: 600; color: #1e40af;">{{ $enr->student->student_id }}</td>
                            <td style="padding: 10px 16px; font-weight: 500; color: #111827;">{{ $enr->student->full_name }}</td>
                            <td style="padding: 10px 16px; text-align: center;">
                                <span style="padding: 2px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;
                                    {{ $enr->student->gender === 'male' ? 'background: #dbeafe; color: #1d4ed8;' : 'background: #fce7f3; color: #be185d;' }}">
                                    {{ ucfirst($enr->student->gender) }}
                                </span>
                            </td>
                            <td style="padding: 10px 16px; text-align: center;">
                                @if($enr->stream)
                                <span style="padding: 2px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: #fef3c7; color: #92400e;">
                                    {{ $enr->stream->name }}
                                </span>
                                @else
                                <span style="color: #9ca3af;">—</span>
                                @endif
                            </td>
                            @foreach($sequences as $sIdx => $seq)
                            @php
                                $seqAvg = $enr->_seq_averages[$sIdx + 1] ?? null;
                                $seqFail = $seqAvg !== null && (float)$seqAvg < 10;
                            @endphp
                            <td style="padding: 10px 16px; text-align: center; font-weight: 500; {{ $seqFail ? 'color: #dc2626;' : 'color: #374151;' }}">
                                {{ $seqAvg !== null ? number_format((float)$seqAvg, 2) : '—' }}
                            </td>
                            @endforeach
                            <td style="padding: 10px 16px; text-align: center; font-weight: 600; {{ $isFail ? 'color: #dc2626;' : 'color: #059669;' }}">
                                {{ $avg !== null ? number_format((float)$avg, 2) . '/20' : '—' }}
                            </td>
                            <td style="padding: 10px 16px; text-align: center; font-weight: 500; color: #6b7280;">
                                {{ $enr->_overall_rank ?? '—' }}
                            </td>
                            <td style="padding: 10px 16px; text-align: center;">
                                <span style="padding: 2px 10px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: #e0e7ff; color: #3730a3;">
                                    {{ $enr->student->batch->name ?? '—' }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- TARGET: Next Enrolment                                             --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    @if($filtered && $enrollments->isNotEmpty())
    <form method="POST" action="{{ route('admin.group-enrol.enrol') }}" id="enrolForm">
        @csrf
        {{-- Hidden: selected student IDs (filled by JS) --}}
        <div id="hiddenStudentIds"></div>

        <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e5e7eb; overflow: hidden;">
            <div style="padding: 16px 24px; background: #f59e0b; border-bottom: 1px solid #d97706; display: flex; align-items: center; gap: 10px;">
                <svg style="width: 20px; height: 20px; color: #fff; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                <span style="font-size: 1rem; font-weight: 600; color: #fff;">{{ __('Next Enrolment') }}</span>
            </div>

            <div style="padding: 24px;">
                <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; align-items: end;">
                    {{-- Target Session --}}
                    <div>
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 4px;">{{ __('Academic Session') }} <span style="color: #ef4444;">*</span></label>
                        <select name="target_session_id" id="tgtSession" required
                            style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; color: #111827; background: #fff; appearance: auto;">
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach($sessions as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Target Form --}}
                    <div>
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 4px;">{{ __('Form') }} <span style="color: #ef4444;">*</span></label>
                        <select name="target_form_id" id="tgtForm" required
                            style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; color: #111827; background: #fff; appearance: auto;">
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach($forms as $f)
                                <option value="{{ $f->id }}" data-has-streams="{{ $f->has_streams ? '1' : '0' }}">{{ $f->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Target Stream --}}
                    <div id="tgtStreamWrap" style="display: none;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 4px;">{{ __('Stream') }}</label>
                        <select name="target_stream_id" id="tgtStream"
                            style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; color: #111827; background: #fff; appearance: auto;">
                            <option value="">{{ __('— Select —') }}</option>
                        </select>
                    </div>

                    {{-- Target Section --}}
                    <div>
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 4px;">{{ __('Section') }} <span style="color: #ef4444;">*</span></label>
                        <select name="target_section_id" id="tgtSection" required
                            style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; color: #111827; background: #fff; appearance: auto;">
                            <option value="">{{ __('— Select —') }}</option>
                        </select>
                    </div>

                    {{-- Target Term --}}
                    <div>
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 4px;">{{ __('Term') }} <span style="color: #ef4444;">*</span></label>
                        <select name="target_term_id" id="tgtTerm" required
                            style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; color: #111827; background: #fff; appearance: auto;">
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach($terms as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Subject Preview --}}
            <div id="subjectPreview" style="display: none; border-top: 1px solid #e5e7eb;">
                <div style="padding: 16px 24px; background: #f8fafc; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 8px;">
                    <svg style="width: 18px; height: 18px; color: #6366f1; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    <span style="font-size: 0.875rem; font-weight: 600; color: #374151;">{{ __('Curriculum Subjects Preview') }}</span>
                    <span id="subjectStats" style="font-size: 0.75rem; color: #6b7280; margin-left: auto;"></span>
                </div>
                <div style="padding: 16px 24px;">
                    <div id="subjectGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 8px;"></div>
                </div>
            </div>

            {{-- Enrol Button --}}
            <div style="padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between;">
                <div style="font-size: 0.875rem; color: #6b7280;">
                    <span id="enrolSummary"></span>
                </div>
                <button type="submit" id="enrolBtn" disabled
                    style="padding: 10px 32px; background: #16a34a; color: #fff; border: none; border-radius: 8px; font-size: 0.875rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; opacity: 0.5; transition: all 0.2s;"
                    onmouseover="if(!this.disabled) this.style.background='#15803d'" onmouseout="this.style.background='#16a34a'">
                    <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    {{ __('Enrol Selected Students') }}
                </button>
            </div>
        </div>
    </form>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ─── Source cascading: Form → Section ───
    const srcForm = document.getElementById('srcForm');
    const srcSection = document.getElementById('srcSection');

    if (srcForm) {
        srcForm.addEventListener('change', function () {
            const formId = this.value;
            srcSection.innerHTML = '<option value="">{{ __("— Select —") }}</option>';
            if (!formId) return;

            fetch(`{{ url('admin/group-enrol/sections') }}/${formId}`)
                .then(r => r.json())
                .then(data => {
                    data.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s.id;
                        opt.textContent = s.name;
                        srcSection.appendChild(opt);
                    });
                });
        });
    }

    // ─── Target cascading: Form → Stream + Section + Subject Preview ───
    const tgtForm = document.getElementById('tgtForm');
    const tgtStream = document.getElementById('tgtStream');
    const tgtStreamWrap = document.getElementById('tgtStreamWrap');
    const tgtSection = document.getElementById('tgtSection');

    if (tgtForm) {
        tgtForm.addEventListener('change', function () {
            const formId = this.value;
            const hasStreams = this.options[this.selectedIndex]?.dataset?.hasStreams === '1';

            // Reset
            tgtSection.innerHTML = '<option value="">{{ __("— Select —") }}</option>';
            tgtStream.innerHTML = '<option value="">{{ __("— Select —") }}</option>';
            tgtStreamWrap.style.display = 'none';
            hideSubjectPreview();

            if (!formId) return;

            // Load sections
            fetch(`{{ url('admin/group-enrol/sections') }}/${formId}`)
                .then(r => r.json())
                .then(data => {
                    data.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s.id;
                        opt.textContent = s.name;
                        tgtSection.appendChild(opt);
                    });
                });

            // Load streams if applicable
            if (hasStreams) {
                tgtStreamWrap.style.display = '';
                fetch(`{{ url('admin/group-enrol/streams') }}/${formId}`)
                    .then(r => r.json())
                    .then(data => {
                        data.forEach(s => {
                            const opt = document.createElement('option');
                            opt.value = s.id;
                            opt.textContent = s.name;
                            tgtStream.appendChild(opt);
                        });
                    });
            }

            loadSubjectPreview();
        });

        tgtStream.addEventListener('change', () => loadSubjectPreview());
    }

    // ─── Subject Preview ───
    function loadSubjectPreview() {
        const formId = tgtForm.value;
        if (!formId) { hideSubjectPreview(); return; }

        const streamId = tgtStream.value || '';
        fetch(`{{ route('admin.group-enrol.preview-subjects') }}?form_id=${formId}&stream_id=${streamId}`)
            .then(r => r.json())
            .then(subjects => {
                if (subjects.length === 0) { hideSubjectPreview(); return; }

                const grid = document.getElementById('subjectGrid');
                const stats = document.getElementById('subjectStats');
                grid.innerHTML = '';

                let coreCount = 0, elCount = 0, totalCoeff = 0;

                subjects.forEach(sub => {
                    const isCore = sub.type === 'core';
                    if (isCore) coreCount++; else elCount++;
                    totalCoeff += parseFloat(sub.coefficient) || 0;

                    const card = document.createElement('div');
                    card.style.cssText = 'display:flex; align-items:center; gap:10px; padding:8px 12px; border-radius:8px; border:1px solid ' + (isCore ? '#dbeafe' : '#fef3c7') + '; background:' + (isCore ? '#eff6ff' : '#fffbeb') + ';';
                    card.innerHTML = `
                        <span style="padding:2px 7px; border-radius:4px; font-size:0.7rem; font-weight:700; letter-spacing:0.5px;
                            background:${isCore ? '#2563eb' : '#f59e0b'}; color:#fff;">${isCore ? '{{ __("CORE") }}' : '{{ __("ELECTIVE") }}'}</span>
                        <span style="font-size:0.8rem; font-weight:500; color:#111827; flex:1;">${sub.name}</span>
                        <span style="font-size:0.75rem; color:#6b7280;">×${parseFloat(sub.coefficient).toFixed(1)}</span>
                    `;
                    grid.appendChild(card);
                });

                stats.textContent = `${coreCount} {{ __('core') }}, ${elCount} {{ __('elective') }} — {{ __('Total Coeff') }}: ${totalCoeff.toFixed(1)}`;
                document.getElementById('subjectPreview').style.display = '';
            });
    }

    function hideSubjectPreview() {
        document.getElementById('subjectPreview').style.display = 'none';
    }

    // ─── Validate enrol form on changes ───
    const enrolBtn = document.getElementById('enrolBtn');

    function validateEnrolForm() {
        if (!enrolBtn) return;
        const selected = document.querySelectorAll('.studentCb:checked').length;
        const tgtOk = document.getElementById('tgtSession')?.value
            && document.getElementById('tgtForm')?.value
            && document.getElementById('tgtSection')?.value
            && document.getElementById('tgtTerm')?.value;

        const ok = selected > 0 && tgtOk;
        enrolBtn.disabled = !ok;
        enrolBtn.style.opacity = ok ? '1' : '0.5';

        const summary = document.getElementById('enrolSummary');
        if (summary) {
            if (selected > 0 && tgtOk) {
                summary.textContent = `{{ __('Ready to enrol') }} ${selected} {{ __('student(s)') }}`;
                summary.style.color = '#16a34a';
                summary.style.fontWeight = '600';
            } else if (selected > 0) {
                summary.textContent = `${selected} {{ __('selected — fill all target fields') }}`;
                summary.style.color = '#f59e0b';
                summary.style.fontWeight = '500';
            } else {
                summary.textContent = '{{ __('Select students above and fill target enrolment') }}';
                summary.style.color = '#6b7280';
                summary.style.fontWeight = '400';
            }
        }
    }

    // Listen for changes on target selects
    ['tgtSession', 'tgtForm', 'tgtSection', 'tgtTerm', 'tgtStream'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', validateEnrolForm);
    });

    // ─── Enrol form submission: inject selected student IDs ───
    const enrolForm = document.getElementById('enrolForm');
    if (enrolForm) {
        enrolForm.addEventListener('submit', function (e) {
            const container = document.getElementById('hiddenStudentIds');
            container.innerHTML = '';
            const checked = document.querySelectorAll('.studentCb:checked');
            if (checked.length === 0) {
                e.preventDefault();
                alert('{{ __("Please select at least one student.") }}');
                return;
            }
            checked.forEach(cb => {
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'student_ids[]';
                inp.value = cb.value;
                container.appendChild(inp);
            });
        });
    }

    // Initial validation
    validateEnrolForm();
});

// ─── Student selection helpers ───
function toggleAllStudents(checked) {
    document.querySelectorAll('.studentCb').forEach(cb => { cb.checked = checked; });
    document.getElementById('selectAllCb').checked = checked;
    updateSelectedCount();
}

function toggleStudentRow(row, studentId) {
    const cb = row.querySelector('.studentCb');
    cb.checked = !cb.checked;
    updateSelectedCount();
}

function updateSelectedCount() {
    const total = document.querySelectorAll('.studentCb').length;
    const selected = document.querySelectorAll('.studentCb:checked').length;
    const counter = document.getElementById('selectedCount');
    if (counter) counter.textContent = selected;

    const selectAll = document.getElementById('selectAllCb');
    if (selectAll) selectAll.checked = (selected === total && total > 0);

    // Highlight selected rows
    document.querySelectorAll('.studentCb').forEach(cb => {
        const row = cb.closest('tr');
        if (row) {
            row.style.background = cb.checked ? '#eff6ff' : (row.dataset.bg || '#fff');
        }
    });

    // Re-validate enrol form
    if (typeof validateEnrolForm === 'function') {
        // call from global scope
    }
    // Dispatch a custom event
    document.dispatchEvent(new Event('selectionChanged'));
}

document.addEventListener('selectionChanged', function () {
    const enrolBtn = document.getElementById('enrolBtn');
    if (!enrolBtn) return;
    const selected = document.querySelectorAll('.studentCb:checked').length;
    const tgtOk = document.getElementById('tgtSession')?.value
        && document.getElementById('tgtForm')?.value
        && document.getElementById('tgtSection')?.value
        && document.getElementById('tgtTerm')?.value;

    const ok = selected > 0 && tgtOk;
    enrolBtn.disabled = !ok;
    enrolBtn.style.opacity = ok ? '1' : '0.5';

    const summary = document.getElementById('enrolSummary');
    if (summary) {
        if (selected > 0 && tgtOk) {
            summary.textContent = `Ready to enrol ${selected} student(s)`;
            summary.style.color = '#16a34a';
            summary.style.fontWeight = '600';
        } else if (selected > 0) {
            summary.textContent = `${selected} selected — fill all target fields`;
            summary.style.color = '#f59e0b';
            summary.style.fontWeight = '500';
        } else {
            summary.textContent = 'Select students above and fill target enrolment';
            summary.style.color = '#6b7280';
            summary.style.fontWeight = '400';
        }
    }
});
</script>
@endpush
