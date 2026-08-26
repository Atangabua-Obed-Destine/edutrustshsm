@extends('layouts.admin')

@section('title', __('Quick Assign Fee'))
@section('breadcrumb', __('Fees > Quick Assign'))

@section('content')
<div style="max-width: 860px;">

    {{-- Header --}}
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 700; color: #1e293b;">{{ __('Quick Assign Fee') }}</h2>
            <p style="font-size: 0.825rem; color: #64748b; margin-top: 2px;">{{ __('Manually assign a fee to an individual student for the current session.') }}</p>
        </div>
    </div>

    {{-- Success Message --}}
    @if(session('success'))
    <div id="successAlert" style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 14px 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
        <div style="width: 26px; height: 26px; background: #22c55e; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <svg width="14" height="14" fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
        </div>
        <span style="font-size: 0.85rem; color: #166534; font-weight: 500;">{{ session('success') }}</span>
        <button onclick="document.getElementById('successAlert').style.display='none'" style="margin-left: auto; background: none; border: none; color: #166534; cursor: pointer; font-size: 1.1rem; padding: 0 4px;">&times;</button>
    </div>
    @endif

    {{-- Validation Errors --}}
    @if($errors->any())
    <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 14px 18px; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
            <svg width="16" height="16" fill="none" stroke="#dc2626" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span style="font-size: 0.85rem; color: #991b1b; font-weight: 600;">{{ __('Please correct the following errors:') }}</span>
        </div>
        <ul style="margin: 0; padding-left: 24px;">
            @foreach($errors->all() as $error)
                <li style="font-size: 0.8rem; color: #991b1b;">{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Duplicate Warning --}}
    @if(session('duplicate_warning'))
    @php $existingFee = session('existing_fee'); @endphp
    <div id="duplicateWarning" style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 18px; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
            <div style="width: 26px; height: 26px; background: #f59e0b; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <svg width="14" height="14" fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <span style="font-size: 0.9rem; color: #92400e; font-weight: 600;">{{ __('Fee Already Assigned') }}</span>
        </div>
        <p style="font-size: 0.83rem; color: #78350f; margin-bottom: 10px;">
            {{ __('This student already has this fee category assigned.') }}
        </p>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; background: #fef3c7; border-radius: 8px; padding: 12px; margin-bottom: 14px;">
            <div>
                <span style="font-size: 0.72rem; color: #92400e; text-transform: uppercase; font-weight: 600;">{{ __('Current Amount') }}</span>
                <p style="font-size: 0.9rem; font-weight: 700; color: #78350f; margin-top: 2px;">{{ number_format($existingFee->original_amount, 0, '.', ',') }} {{ __('CFA') }}</p>
            </div>
            <div>
                <span style="font-size: 0.72rem; color: #92400e; text-transform: uppercase; font-weight: 600;">{{ __('Paid') }}</span>
                <p style="font-size: 0.9rem; font-weight: 700; color: #78350f; margin-top: 2px;">{{ number_format($existingFee->paid_amount, 0, '.', ',') }} {{ __('CFA') }}</p>
            </div>
            <div>
                <span style="font-size: 0.72rem; color: #92400e; text-transform: uppercase; font-weight: 600;">{{ __('Status') }}</span>
                <p style="font-size: 0.9rem; font-weight: 700; color: #78350f; margin-top: 2px; text-transform: capitalize;">{{ __($existingFee->status) }}</p>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.quick-assign.store') }}" style="display: flex; gap: 10px;">
            @csrf
            <input type="hidden" name="student_enrollment_id" value="{{ old('student_enrollment_id') }}">
            <input type="hidden" name="fee_category_id" value="{{ old('fee_category_id') }}">
            <input type="hidden" name="amount" value="{{ old('amount') }}">
            <input type="hidden" name="due_date" value="{{ old('due_date') }}">
            <input type="hidden" name="notes" value="{{ old('notes') }}">
            <input type="hidden" name="override" value="1">
            <button type="submit"
                    style="padding: 8px 20px; background: #f59e0b; color: #fff; border: none; border-radius: 8px; font-size: 0.82rem; font-weight: 600; cursor: pointer;"
                    onmouseover="this.style.background='#d97706'" onmouseout="this.style.background='#f59e0b'">
                {{ __('Override & Update') }}
            </button>
            <button type="button" onclick="document.getElementById('duplicateWarning').style.display='none'"
                    style="padding: 8px 20px; background: #e5e7eb; color: #374151; border: none; border-radius: 8px; font-size: 0.82rem; font-weight: 600; cursor: pointer;"
                    onmouseover="this.style.background='#d1d5db'" onmouseout="this.style.background='#e5e7eb'">
                {{ __('Cancel') }}
            </button>
        </form>
    </div>
    @endif

    {{-- Assignment Form Card --}}
    <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; padding: 28px;">

        {{-- Card Header --}}
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #f1f5f9;">
            <div style="width: 32px; height: 32px; background: #1e293b; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            </div>
            <div>
                <h3 style="font-size: 1rem; font-weight: 600; color: #334155;">{{ __('Assign Fee Details') }}</h3>
                <p style="font-size: 0.75rem; color: #94a3b8;">{{ __('Current Session') }}: <strong>{{ $currentSession->name ?? '—' }}</strong></p>
            </div>
        </div>

        @if(!$currentSession)
            <div style="text-align: center; padding: 40px 20px;">
                <svg width="40" height="40" fill="none" stroke="#94a3b8" viewBox="0 0 24 24" style="margin: 0 auto 12px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p style="font-size: 0.9rem; color: #64748b; font-weight: 500;">{{ __('No active academic session found. Please activate a session first.') }}</p>
            </div>
        @else
        <form method="POST" action="{{ route('admin.quick-assign.store') }}" id="quickAssignForm">
            @csrf

            {{-- Row 1: Student Search --}}
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">
                    {{ __('Student') }} <span style="color: #ef4444;">*</span>
                </label>
                <div style="position: relative;" id="studentSearchWrapper">
                    <input type="text" id="studentSearch" placeholder="{{ __('Type student ID or name to search...') }}" autocomplete="off"
                           style="width: 100%; padding: 10px 14px 10px 38px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; background: #fff;"
                           onfocus="this.style.borderColor='#0ea5e9'; this.style.boxShadow='0 0 0 3px rgba(14,165,233,0.1)'"
                           onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
                    <svg style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); pointer-events: none;" width="16" height="16" fill="none" stroke="#94a3b8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <div id="studentDropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.12); max-height: 260px; overflow-y: auto; z-index: 50; margin-top: 4px;"></div>
                    <input type="hidden" name="student_enrollment_id" id="studentEnrollmentId" value="{{ old('student_enrollment_id') }}">
                    <input type="hidden" id="studentFormId" value="">
                    <input type="hidden" id="studentStreamId" value="">
                </div>

                {{-- Selected Student Info Card --}}
                <div id="selectedStudentCard" style="display: none; margin-top: 10px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 12px 16px;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 36px; height: 36px; background: #0ea5e9; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem;" id="studentAvatar"></div>
                            <div>
                                <p style="font-size: 0.85rem; font-weight: 600; color: #0c4a6e;" id="selectedStudentName"></p>
                                <p style="font-size: 0.75rem; color: #0369a1;" id="selectedStudentDetails"></p>
                            </div>
                        </div>
                        <button type="button" onclick="clearStudent()" style="background: none; border: none; color: #0369a1; cursor: pointer; padding: 4px;" title="{{ __('Clear') }}">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Row 2: Fee Category + Amount --}}
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                {{-- Fee Category --}}
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">
                        {{ __('Fee Category') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="fee_category_id" id="feeCategorySelect" required
                            style="width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; appearance: auto; -webkit-appearance: menulist; background: #fff;"
                            onfocus="this.style.borderColor='#0ea5e9'; this.style.boxShadow='0 0 0 3px rgba(14,165,233,0.1)'"
                            onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
                        <option value="">-- {{ __('Select Fee Category') }} --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('fee_category_id') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }} {{ $cat->is_mandatory ? '★' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Amount --}}
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">
                        {{ __('Amount') }} ({{ __('CFA') }}) <span style="color: #ef4444;">*</span>
                    </label>
                    <div style="position: relative;">
                        <input type="number" name="amount" id="amountInput" min="1" step="1" required
                               value="{{ old('amount') }}" placeholder="0"
                               style="width: 100%; padding: 10px 14px 10px 50px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; background: #fff;"
                               onfocus="this.style.borderColor='#0ea5e9'; this.style.boxShadow='0 0 0 3px rgba(14,165,233,0.1)'"
                               onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
                        <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 0.8rem; font-weight: 600; color: #64748b;">CFA</span>
                    </div>
                    <p id="suggestedAmountHint" style="display: none; font-size: 0.72rem; color: #0ea5e9; margin-top: 4px; cursor: pointer;"
                       onclick="useSuggestedAmount()"></p>
                </div>
            </div>

            {{-- Fee Status Warning (inline) --}}
            <div id="feeExistsInline" style="display: none; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 10px 14px; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; gap: 6px;">
                    <svg width="14" height="14" fill="none" stroke="#d97706" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span id="feeExistsMessage" style="font-size: 0.78rem; color: #92400e; font-weight: 500;"></span>
                </div>
            </div>

            {{-- Row 3: Due Date + Notes --}}
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                {{-- Due Date --}}
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">
                        {{ __('Due Date') }}
                    </label>
                    <input type="date" name="due_date" id="dueDateInput" value="{{ old('due_date') }}"
                           style="width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; background: #fff;"
                           onfocus="this.style.borderColor='#0ea5e9'; this.style.boxShadow='0 0 0 3px rgba(14,165,233,0.1)'"
                           onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
                    <p style="font-size: 0.7rem; color: #94a3b8; margin-top: 3px;">{{ __('Leave empty if no specific deadline.') }}</p>
                </div>

                {{-- Notes --}}
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">
                        {{ __('Notes') }}
                    </label>
                    <input type="text" name="notes" id="notesInput" value="{{ old('notes') }}" placeholder="{{ __('Optional note or reason...') }}" maxlength="500"
                           style="width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; background: #fff;"
                           onfocus="this.style.borderColor='#0ea5e9'; this.style.boxShadow='0 0 0 3px rgba(14,165,233,0.1)'"
                           onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
                </div>
            </div>

            {{-- Divider --}}
            <div style="border-top: 1px solid #f1f5f9; padding-top: 20px; display: flex; align-items: center; justify-content: space-between;">
                {{-- Summary Preview --}}
                <div id="assignSummary" style="display: none;">
                    <p style="font-size: 0.75rem; color: #64748b; font-weight: 500;">{{ __('Summary') }}:</p>
                    <p style="font-size: 0.83rem; color: #1e293b; font-weight: 600;" id="summaryText"></p>
                </div>
                <div style="flex: 1;"></div>

                {{-- Action Buttons --}}
                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="resetForm()"
                            style="padding: 10px 24px; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer;"
                            onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                        {{ __('Reset') }}
                    </button>
                    <button type="submit" id="assignBtn"
                            style="padding: 10px 28px; background: #0ea5e9; color: #fff; border: none; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;"
                            onmouseover="this.style.background='#0284c7'" onmouseout="this.style.background='#0ea5e9'">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        {{ __('Assign Fee') }}
                    </button>
                </div>
            </div>
        </form>
        @endif
    </div>

    {{-- Help Tips --}}
    <div style="margin-top: 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px 20px;">
        <h4 style="font-size: 0.82rem; font-weight: 600; color: #475569; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ __('Quick Tips') }}
        </h4>
        <ul style="margin: 0; padding-left: 18px; font-size: 0.78rem; color: #64748b; line-height: 1.7;">
            <li>{{ __('Search for a student by their Student ID or name.') }}</li>
            <li>{{ __('The amount is auto-filled from the fee structure if one exists for the student\'s form/stream.') }}</li>
            <li>{{ __('If the fee is already assigned, you can choose to override it with a new amount.') }}</li>
            <li>{{ __('Mandatory fee categories are marked with a ★ symbol.') }}</li>
        </ul>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let searchTimeout = null;
    let suggestedAmount = null;

    const searchInput = document.getElementById('studentSearch');
    const dropdown = document.getElementById('studentDropdown');
    const enrollmentInput = document.getElementById('studentEnrollmentId');
    const formIdInput = document.getElementById('studentFormId');
    const streamIdInput = document.getElementById('studentStreamId');
    const selectedCard = document.getElementById('selectedStudentCard');
    const categorySelect = document.getElementById('feeCategorySelect');
    const amountInput = document.getElementById('amountInput');
    const feeExistsInline = document.getElementById('feeExistsInline');

    // Student search
    searchInput.addEventListener('input', function () {
        const q = this.value.trim();
        clearTimeout(searchTimeout);

        if (q.length < 2) {
            dropdown.style.display = 'none';
            return;
        }

        searchTimeout = setTimeout(() => {
            fetch("{{ route('admin.quick-assign.search-students') }}?q=" + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    if (data.length === 0) {
                        dropdown.innerHTML = '<div style="padding: 14px 16px; font-size: 0.83rem; color: #94a3b8; text-align: center;">{{ __("No students found.") }}</div>';
                        dropdown.style.display = 'block';
                        return;
                    }
                    dropdown.innerHTML = data.map(s =>
                        `<div style="padding: 10px 16px; cursor: pointer; font-size: 0.83rem; border-bottom: 1px solid #f1f5f9;"
                              onmouseover="this.style.background='#f0f9ff'" onmouseout="this.style.background='#fff'"
                              onclick="selectStudent(${s.enrollment_id}, '${escapeHtml(s.student_id)}', '${escapeHtml(s.name)}', '${escapeHtml(s.class)}', '${escapeHtml(s.stream)}', ${s.form_id || 'null'}, ${s.stream_id || 'null'})">
                            <span style="font-weight: 600; color: #1e293b;">${escapeHtml(s.student_id)}</span>
                            <span style="color: #64748b;"> — ${escapeHtml(s.name)}</span>
                            <br><span style="font-size: 0.72rem; color: #94a3b8;">${escapeHtml(s.class)}${s.stream ? ' / ' + escapeHtml(s.stream) : ''}</span>
                        </div>`
                    ).join('');
                    dropdown.style.display = 'block';
                });
        }, 300);
    });

    // Close dropdown on outside click
    document.addEventListener('click', function (e) {
        if (!document.getElementById('studentSearchWrapper').contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });

    function selectStudent(enrollmentId, studentId, name, cls, stream, formId, streamId) {
        enrollmentInput.value = enrollmentId;
        formIdInput.value = formId || '';
        streamIdInput.value = streamId || '';

        document.getElementById('studentAvatar').textContent = name.charAt(0).toUpperCase();
        document.getElementById('selectedStudentName').textContent = name;
        document.getElementById('selectedStudentDetails').textContent = studentId + ' • ' + cls + (stream ? ' / ' + stream : '');

        searchInput.style.display = 'none';
        selectedCard.style.display = 'block';
        dropdown.style.display = 'none';

        // If category already selected, check fee
        if (categorySelect.value) {
            checkFeeStatus();
        }

        updateSummary();
    }

    function clearStudent() {
        enrollmentInput.value = '';
        formIdInput.value = '';
        streamIdInput.value = '';
        searchInput.value = '';
        searchInput.style.display = 'block';
        selectedCard.style.display = 'none';
        feeExistsInline.style.display = 'none';
        document.getElementById('suggestedAmountHint').style.display = 'none';
        suggestedAmount = null;
        updateSummary();
    }

    // Fee category change → check fee + get suggested amount
    categorySelect.addEventListener('change', function () {
        if (enrollmentInput.value && this.value) {
            checkFeeStatus();
        } else {
            feeExistsInline.style.display = 'none';
            document.getElementById('suggestedAmountHint').style.display = 'none';
            suggestedAmount = null;
        }
        updateSummary();
    });

    amountInput.addEventListener('input', updateSummary);

    function checkFeeStatus() {
        const enrollmentId = enrollmentInput.value;
        const categoryId = categorySelect.value;
        const formId = formIdInput.value;
        const streamId = streamIdInput.value;

        if (!enrollmentId || !categoryId) return;

        const params = new URLSearchParams({
            enrollment_id: enrollmentId,
            category_id: categoryId,
            form_id: formId,
            stream_id: streamId,
        });

        fetch("{{ route('admin.quick-assign.check-fee') }}?" + params)
            .then(r => r.json())
            .then(data => {
                // Show existing fee warning
                if (data.exists) {
                    const ex = data.existing;
                    const msg = "{{ __('This fee is already assigned: :amount CFA (:status). Submitting will prompt to override.') }}"
                        .replace(':amount', Number(ex.original_amount).toLocaleString())
                        .replace(':status', ex.status);
                    document.getElementById('feeExistsMessage').textContent = msg;
                    feeExistsInline.style.display = 'block';
                } else {
                    feeExistsInline.style.display = 'none';
                }

                // Show suggested amount
                const hint = document.getElementById('suggestedAmountHint');
                if (data.suggested_amount) {
                    suggestedAmount = data.suggested_amount;
                    hint.textContent = "💡 {{ __('Fee structure suggests') }}: " + Number(data.suggested_amount).toLocaleString() + " CFA — {{ __('click to use') }}";
                    hint.style.display = 'block';

                    // Auto-fill if amount is empty
                    if (!amountInput.value) {
                        amountInput.value = Math.round(data.suggested_amount);
                        updateSummary();
                    }
                } else {
                    hint.style.display = 'none';
                    suggestedAmount = null;
                }
            });
    }

    function useSuggestedAmount() {
        if (suggestedAmount) {
            amountInput.value = Math.round(suggestedAmount);
            updateSummary();
        }
    }

    function updateSummary() {
        const summaryDiv = document.getElementById('assignSummary');
        const summaryText = document.getElementById('summaryText');
        const studentName = document.getElementById('selectedStudentName')?.textContent || '';
        const catOption = categorySelect.options[categorySelect.selectedIndex];
        const catName = catOption && catOption.value ? catOption.text.trim() : '';
        const amount = amountInput.value;

        if (studentName && catName && amount) {
            summaryText.textContent = catName + ' → ' + studentName + ' = ' + Number(amount).toLocaleString() + ' CFA';
            summaryDiv.style.display = 'block';
        } else {
            summaryDiv.style.display = 'none';
        }
    }

    function resetForm() {
        clearStudent();
        categorySelect.value = '';
        amountInput.value = '';
        document.getElementById('dueDateInput').value = '';
        document.getElementById('notesInput').value = '';
        feeExistsInline.style.display = 'none';
        document.getElementById('suggestedAmountHint').style.display = 'none';
        document.getElementById('assignSummary').style.display = 'none';
        suggestedAmount = null;
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // Form submit validation
    document.getElementById('quickAssignForm')?.addEventListener('submit', function (e) {
        if (!enrollmentInput.value) {
            e.preventDefault();
            alert("{{ __('Please select a student first.') }}");
            searchInput.focus();
        }
    });
</script>
@endpush
