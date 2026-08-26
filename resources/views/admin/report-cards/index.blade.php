@extends('layouts.admin')

@section('title', __('Report Cards'))

@section('content')
<div style="display: flex; flex-direction: column; gap: 24px;">

    {{-- Page Header --}}
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: #111827;">{{ __('Report Cards') }}</h1>
        <p style="margin-top: 4px; font-size: 0.875rem; color: #6b7280;">{{ __('Generate and manage term report cards') }}</p>
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

    {{-- Filter Card --}}
    <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e5e7eb; padding: 24px;">
        <form method="GET" action="{{ route('admin.report-cards.index') }}" id="reportCardFilterForm">
            <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; align-items: end;">
                {{-- Academic Session --}}
                <div>
                    <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 4px;">{{ __('Academic Session') }}</label>
                    <select name="academic_session_id" id="sessionSelect"
                        style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; color: #111827; background: #fff; appearance: auto; -webkit-appearance: menulist;">
                        <option value="">{{ __('— Select Session —') }}</option>
                        @foreach($sessions as $s)
                            <option value="{{ $s->id }}" {{ $sessionId == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Form --}}
                <div>
                    <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 4px;">{{ __('Form') }}</label>
                    <select name="form_id" id="formSelect"
                        style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; color: #111827; background: #fff; appearance: auto; -webkit-appearance: menulist;">
                        <option value="">{{ __('— Select Form —') }}</option>
                        @foreach($forms as $f)
                            <option value="{{ $f->id }}" {{ $formId == $f->id ? 'selected' : '' }}>{{ $f->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Section (cascading from Form) --}}
                <div>
                    <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 4px;">{{ __('Section') }}</label>
                    <select name="class_section_id" id="sectionSelect"
                        style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; color: #111827; background: #fff; appearance: auto; -webkit-appearance: menulist;">
                        <option value="">{{ __('— Select Section —') }}</option>
                        @foreach($classSections as $cs)
                            <option value="{{ $cs->id }}" {{ $classSectionId == $cs->id ? 'selected' : '' }}>{{ $cs->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Term --}}
                <div>
                    <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 4px;">{{ __('Term') }}</label>
                    <select name="term_id" id="termSelect"
                        style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; color: #111827; background: #fff; appearance: auto; -webkit-appearance: menulist;">
                        <option value="">{{ __('— Select Term —') }}</option>
                        @foreach($terms as $t)
                            <option value="{{ $t->id }}" {{ $termId == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Load Button --}}
                <div>
                    <button type="submit" id="loadBtn"
                        style="width: 100%; padding: 9px 16px; background: #1e293b; color: #fff; border: none; border-radius: 8px; font-size: 0.875rem; font-weight: 600; cursor: pointer;"
                        onmouseover="this.style.background='#334155'" onmouseout="this.style.background='#1e293b'">
                        <span style="display: inline-flex; align-items: center; gap: 6px;">
                            <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            {{ __('Load') }}
                        </span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    @if($classSectionId && $termId)
    {{-- Action Bar --}}
    <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
        {{-- Generate / Recalculate --}}
        <form method="POST" action="{{ route('admin.report-cards.generate') }}" onsubmit="return confirm('{{ __('This will (re)calculate all report cards for this class and term. Proceed?') }}')">
            @csrf
            <input type="hidden" name="academic_session_id" value="{{ $sessionId }}">
            <input type="hidden" name="form_id" value="{{ $formId }}">
            <input type="hidden" name="class_section_id" value="{{ $classSectionId }}">
            <input type="hidden" name="term_id" value="{{ $termId }}">
            <button type="submit"
                style="display: inline-flex; align-items: center; gap: 6px; padding: 9px 20px; background: #4f46e5; color: #fff; border: none; border-radius: 8px; font-size: 0.875rem; font-weight: 600; cursor: pointer;"
                onmouseover="this.style.background='#4338ca'" onmouseout="this.style.background='#4f46e5'">
                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                {{ __('Generate / Recalculate') }}
            </button>
        </form>

        @if($results->count() > 0)
        {{-- Publish All --}}
        <form method="POST" action="{{ route('admin.report-cards.publish') }}">
            @csrf
            <input type="hidden" name="class_section_id" value="{{ $classSectionId }}">
            <input type="hidden" name="term_id" value="{{ $termId }}">
            <button type="submit"
                style="display: inline-flex; align-items: center; gap: 6px; padding: 9px 20px; background: #16a34a; color: #fff; border: none; border-radius: 8px; font-size: 0.875rem; font-weight: 600; cursor: pointer;"
                onmouseover="this.style.background='#15803d'" onmouseout="this.style.background='#16a34a'">
                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ __('Publish All') }}
            </button>
        </form>
        @endif
    </div>

    {{-- Results Table --}}
    @if($results->count() > 0)
    <form id="bulkZipForm" method="POST" action="{{ route('admin.report-cards.bulk-download') }}">
        @csrf
    <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e5e7eb; overflow: hidden;">
        {{-- Table Header Bar --}}
        <div style="padding: 16px 24px; border-bottom: 1px solid #e5e7eb; background: #f9fafb; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
            <div>
                <h2 style="font-size: 1.1rem; font-weight: 600; color: #111827;">{{ $selectedClass->name ?? '' }} — {{ $selectedTerm->name ?? '' }}</h2>
                <p style="font-size: 0.8rem; color: #6b7280; margin-top: 2px;">
                    {{ $results->count() }} {{ $results->count() !== 1 ? __('report cards') : __('report card') }}
                    <span id="bulkSelectionInfo" style="display:none; margin-left: 8px; padding: 2px 8px; background: #e0f2fe; color: #0369a1; border-radius: 999px; font-weight: 600;">
                        <span id="bulkSelectedCount">0</span> {{ __('selected') }}
                    </span>
                </p>
            </div>
            <div style="display: flex; align-items: center; gap: 12px;">
                {{-- Bulk download button --}}
                <button type="submit" id="bulkDownloadBtn" disabled
                    style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: #94a3b8; color: #fff; border: none; border-radius: 8px; font-size: 0.8rem; font-weight: 600; cursor: not-allowed; transition: background 0.15s;">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>{{ __('Download Selected as PDF (ZIP)') }}</span>
                    <span id="bulkBtnBadge" style="display:none; padding: 1px 7px; background: rgba(255,255,255,0.25); border-radius: 999px; font-size: 0.7rem;">0</span>
                </button>
                @if($results->first())
                <div style="text-align: right; font-size: 0.8rem;">
                    <span style="color: #6b7280;">{{ __('Class Avg:') }}</span>
                    <span style="font-weight: 700; color: #111827;">{{ number_format($results->first()->class_average, 2) }}/20</span>
                </div>
                @endif
            </div>
        </div>

        {{-- Table --}}
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f9fafb;">
                        <th style="padding: 10px 16px; text-align: center; width: 44px;">
                            <input type="checkbox" id="bulkSelectAll" title="{{ __('Select all') }}"
                                style="width: 16px; height: 16px; cursor: pointer; accent-color: #0ea5e9;">
                        </th>
                        <th style="padding: 10px 16px; text-align: center; font-size: 0.75rem; font-weight: 600; color: #4b5563; text-transform: uppercase; letter-spacing: 0.05em; width: 60px;">{{ __('S/N') }}</th>
                        <th style="padding: 10px 16px; text-align: left; font-size: 0.75rem; font-weight: 600; color: #4b5563; text-transform: uppercase; letter-spacing: 0.05em; width: 130px;">{{ __('Student ID') }}</th>
                        <th style="padding: 10px 16px; text-align: left; font-size: 0.75rem; font-weight: 600; color: #4b5563; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Student Name') }}</th>
                        <th style="padding: 10px 16px; text-align: left; font-size: 0.75rem; font-weight: 600; color: #4b5563; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Class') }}</th>
                        <th style="padding: 10px 16px; text-align: center; font-size: 0.75rem; font-weight: 600; color: #4b5563; text-transform: uppercase; letter-spacing: 0.05em; width: 80px;">{{ __('Average') }}</th>
                        <th style="padding: 10px 16px; text-align: center; font-size: 0.75rem; font-weight: 600; color: #4b5563; text-transform: uppercase; letter-spacing: 0.05em; width: 60px;">{{ __('Rank') }}</th>
                        <th style="padding: 10px 16px; text-align: center; font-size: 0.75rem; font-weight: 600; color: #4b5563; text-transform: uppercase; letter-spacing: 0.05em; width: 80px;">{{ __('Status') }}</th>
                        <th style="padding: 10px 16px; text-align: center; font-size: 0.75rem; font-weight: 600; color: #4b5563; text-transform: uppercase; letter-spacing: 0.05em; width: 160px;">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($results as $idx => $result)
                    @php $rowBg = $idx % 2 === 0 ? '#ffffff' : '#f9fafb'; @endphp
                    <tr data-row style="background: {{ $rowBg }}; border-bottom: 1px solid #f3f4f6;"
                        onmouseover="this.style.background='#f0f4ff'" onmouseout="this.style.background=this.dataset.checked==='1' ? '#eff6ff' : '{{ $rowBg }}'"
                        data-default-bg="{{ $rowBg }}">
                        <td style="padding: 10px 16px; text-align: center;">
                            <input type="checkbox" class="bulk-check" name="term_result_ids[]" value="{{ $result->id }}"
                                style="width: 16px; height: 16px; cursor: pointer; accent-color: #0ea5e9;">
                        </td>
                        <td style="padding: 10px 16px; text-align: center; font-size: 0.875rem; color: #6b7280;">{{ $idx + 1 }}</td>
                        <td style="padding: 10px 16px; font-size: 0.875rem; color: #111827; font-weight: 500;">{{ $result->enrollment->student->student_id }}</td>
                        <td style="padding: 10px 16px; font-size: 0.875rem; color: #111827;">{{ $result->enrollment->student->last_name }} {{ $result->enrollment->student->first_name }}</td>
                        <td style="padding: 10px 16px; font-size: 0.875rem; color: #6b7280;">{{ $result->enrollment->classSection->name ?? '—' }}</td>
                        <td style="padding: 10px 16px; text-align: center; font-size: 0.875rem; font-weight: 700; color: {{ $result->term_average >= 10 ? '#15803d' : '#dc2626' }};">
                            {{ number_format($result->term_average, 2) }}
                        </td>
                        <td style="padding: 10px 16px; text-align: center;">
                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; font-size: 0.8rem; font-weight: 700;
                                {{ $result->class_rank <= 3 ? 'background: #fef3c7; color: #92400e;' : 'background: #f3f4f6; color: #374151;' }}">
                                {{ $result->class_rank ?? '-' }}
                            </span>
                        </td>
                        <td style="padding: 10px 16px; text-align: center;">
                            @if($result->is_published)
                            <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.75rem; font-weight: 500; color: #16a34a;">
                                <svg style="width: 14px; height: 14px;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                {{ __('Published') }}
                            </span>
                            @else
                            <span style="font-size: 0.75rem; color: #9ca3af;">{{ __('Draft') }}</span>
                            @endif
                        </td>
                        <td style="padding: 10px 16px; text-align: center;">
                            <div style="display: inline-flex; gap: 6px;">
                                {{-- Print --}}
                                <a href="{{ route('admin.report-cards.show', $result) }}" target="_blank"
                                    style="display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; background: #0ea5e9; color: #fff; border-radius: 6px; font-size: 0.75rem; font-weight: 600; text-decoration: none; cursor: pointer;"
                                    onmouseover="this.style.background='#0284c7'" onmouseout="this.style.background='#0ea5e9'">
                                    <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                    {{ __('Print') }}
                                </a>
                                {{-- Download --}}
                                <a href="{{ route('admin.report-cards.show', $result) }}?download=1" target="_blank"
                                    style="display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; background: #7c3aed; color: #fff; border-radius: 6px; font-size: 0.75rem; font-weight: 600; text-decoration: none; cursor: pointer;"
                                    onmouseover="this.style.background='#6d28d9'" onmouseout="this.style.background='#7c3aed'">
                                    <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    {{ __('Download') }}
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    </form>
    @else
    {{-- No results after loading --}}
    <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e5e7eb; padding: 48px; text-align: center;">
        <svg style="width: 64px; height: 64px; color: #d1d5db; margin: 0 auto 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <h3 style="font-size: 1.1rem; font-weight: 500; color: #111827; margin-bottom: 4px;">{{ __('No Report Cards Found') }}</h3>
        <p style="color: #6b7280; font-size: 0.875rem;">{{ __('Click "Generate / Recalculate" to compute results for this class and term.') }}</p>
    </div>
    @endif

    @else
    {{-- Initial empty state --}}
    <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e5e7eb; padding: 48px; text-align: center;">
        <svg style="width: 64px; height: 64px; color: #d1d5db; margin: 0 auto 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <h3 style="font-size: 1.1rem; font-weight: 500; color: #111827; margin-bottom: 4px;">{{ __('Select Filters to Begin') }}</h3>
        <p style="color: #6b7280; font-size: 0.875rem;">{{ __('Choose an academic session, form, section, and term above, then click Load to view report cards.') }}</p>
    </div>
    @endif

</div>

@push('scripts')
<script>
(function() {
    const baseUrl = @json(url('admin/report-cards'));
    const formSelect = document.getElementById('formSelect');
    const sectionSelect = document.getElementById('sectionSelect');

    // Cascading: Form → Sections
    formSelect.addEventListener('change', function() {
        const formId = this.value;
        sectionSelect.innerHTML = '<option value="">{{ __("— Select Section —") }}</option>';
        if (!formId) return;

        fetch(baseUrl + '/sections-by-form/' + formId)
            .then(r => r.json())
            .then(sections => {
                sections.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = s.name;
                    sectionSelect.appendChild(opt);
                });
            });
    });

    // ====== Bulk-select & ZIP download ======
    const selectAll = document.getElementById('bulkSelectAll');
    const checks = document.querySelectorAll('.bulk-check');
    const downloadBtn = document.getElementById('bulkDownloadBtn');
    const selectionInfo = document.getElementById('bulkSelectionInfo');
    const countLabel = document.getElementById('bulkSelectedCount');
    const btnBadge = document.getElementById('bulkBtnBadge');
    const bulkForm = document.getElementById('bulkZipForm');

    function refreshSelection() {
        if (!checks.length) return;
        let n = 0;
        checks.forEach(cb => {
            const tr = cb.closest('tr');
            if (cb.checked) {
                n++;
                tr.dataset.checked = '1';
                tr.style.background = '#eff6ff';
            } else {
                tr.dataset.checked = '0';
                tr.style.background = tr.dataset.defaultBg;
            }
        });
        countLabel.textContent = n;
        btnBadge.textContent = n;
        if (n > 0) {
            selectionInfo.style.display = 'inline-block';
            btnBadge.style.display = 'inline-block';
            downloadBtn.disabled = false;
            downloadBtn.style.cursor = 'pointer';
            downloadBtn.style.background = '#0ea5e9';
            downloadBtn.onmouseover = () => downloadBtn.style.background = '#0284c7';
            downloadBtn.onmouseout = () => downloadBtn.style.background = '#0ea5e9';
        } else {
            selectionInfo.style.display = 'none';
            btnBadge.style.display = 'none';
            downloadBtn.disabled = true;
            downloadBtn.style.cursor = 'not-allowed';
            downloadBtn.style.background = '#94a3b8';
            downloadBtn.onmouseover = null;
            downloadBtn.onmouseout = null;
        }
        if (selectAll) {
            selectAll.checked = n === checks.length && n > 0;
            selectAll.indeterminate = n > 0 && n < checks.length;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checks.forEach(cb => cb.checked = this.checked);
            refreshSelection();
        });
    }
    checks.forEach(cb => cb.addEventListener('change', refreshSelection));

    if (bulkForm) {
        bulkForm.addEventListener('submit', function(e) {
            const n = document.querySelectorAll('.bulk-check:checked').length;
            if (n === 0) {
                e.preventDefault();
                return;
            }
            // Brief loading state
            downloadBtn.disabled = true;
            downloadBtn.style.opacity = '0.7';
                downloadBtn.querySelector('span').textContent = '{{ __("Generating PDFs...") }}';
            // Restore after a bit (file download is async)
            setTimeout(() => {
                downloadBtn.disabled = false;
                downloadBtn.style.opacity = '1';
                downloadBtn.querySelector('span').textContent = '{{ __("Download Selected as PDF (ZIP)") }}';
            }, 8000);
        });
    }
})();
</script>
@endpush
@endsection
