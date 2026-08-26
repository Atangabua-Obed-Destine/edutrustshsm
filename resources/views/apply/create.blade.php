@extends('layouts.apply')
@section('title', __('New Application'))

@push('styles')
<style>
    .step-indicator { display: flex; gap: 0; margin-bottom: 28px; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
    .step-item { flex: 1; text-align: center; padding: 14px 8px; cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.2s; }
    .step-item:hover { background: #f8fafc; }
    .step-item.active { background: #eff6ff; border-bottom-color: #0ea5e9; }
    .step-item .step-num { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; background: #e2e8f0; color: #64748b; font-size: 0.75rem; font-weight: 700; margin-bottom: 4px; }
    .step-item.active .step-num { background: #0ea5e9; color: #fff; }
    .step-item.done .step-num { background: #22c55e; color: #fff; }
    .step-item .step-label { display: block; font-size: 0.72rem; color: #64748b; font-weight: 500; }
    .step-item.active .step-label { color: #0284c7; font-weight: 600; }
    .step-panel { display: none; }
    .step-panel.active { display: block; }
    .form-group { margin-bottom: 14px; }
    .form-label { display: block; font-size: 0.78rem; font-weight: 600; color: #374151; margin-bottom: 5px; }
    .form-label .req { color: #ef4444; }
    .form-input { width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 7px; font-size: 0.85rem; outline: none; transition: border 0.2s, box-shadow 0.2s; font-family: inherit; }
    .form-input:focus { border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14,165,233,0.1); }
    .form-input.error { border-color: #ef4444; }
    .form-select { width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 7px; font-size: 0.85rem; outline: none; background: #fff; cursor: pointer; transition: border 0.2s; font-family: inherit; }
    .form-select:focus { border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14,165,233,0.1); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }
    .section-title { font-size: 0.92rem; font-weight: 700; color: #0f172a; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0; }
    .file-input-wrap { position: relative; }
    .file-input-wrap input[type="file"] { width: 100%; padding: 8px 12px; border: 1px dashed #d1d5db; border-radius: 7px; font-size: 0.82rem; background: #f8fafc; cursor: pointer; }
    .file-hint { font-size: 0.7rem; color: #94a3b8; margin-top: 3px; }
    @media (max-width: 640px) {
        .form-row, .form-row-3 { grid-template-columns: 1fr; }
        .step-item .step-label { display: none; }
    }
</style>
@endpush

@section('content')
<div style="margin-bottom: 20px;">
    <a href="{{ route('apply.dashboard') }}" style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.82rem; color: #64748b;"
       onmouseover="this.style.color='#0ea5e9'" onmouseout="this.style.color='#64748b'">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        {{ __('Back to Dashboard') }}
    </a>
</div>

<div style="text-align: center; margin-bottom: 24px;">
    <h1 style="font-size: 1.35rem; font-weight: 700; color: #0f172a;">{{ __('Admission Application') }}</h1>
    <p style="font-size: 0.82rem; color: #64748b; margin-top: 4px;">{{ __('Session:') }} <strong>{{ $currentSession->name }}</strong></p>
</div>

{{-- Global Errors --}}
@if($errors->any())
<div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 14px; margin-bottom: 20px;">
    <div style="font-size: 0.82rem; font-weight: 600; color: #dc2626; margin-bottom: 6px;">{{ __('Please correct the following errors:') }}</div>
    <ul style="list-style: none; padding: 0; margin: 0;">
        @foreach($errors->all() as $error)
        <li style="font-size: 0.78rem; color: #dc2626; padding: 2px 0;">• {{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('apply.store') }}" enctype="multipart/form-data" id="applicationForm">
    @csrf

    {{-- Step Indicators --}}
    <div class="step-indicator">
        <div class="step-item active" data-step="1" onclick="goToStep(1)">
            <span class="step-num">1</span>
            <span class="step-label">{{ __('Class') }}</span>
        </div>
        <div class="step-item" data-step="2" onclick="goToStep(2)">
            <span class="step-num">2</span>
            <span class="step-label">{{ __('Personal') }}</span>
        </div>
        <div class="step-item" data-step="3" onclick="goToStep(3)">
            <span class="step-num">3</span>
            <span class="step-label">{{ __('Contact') }}</span>
        </div>
        <div class="step-item" data-step="4" onclick="goToStep(4)">
            <span class="step-num">4</span>
            <span class="step-label">{{ __('Family') }}</span>
        </div>
        <div class="step-item" data-step="5" onclick="goToStep(5)">
            <span class="step-num">5</span>
            <span class="step-label">{{ __('Documents') }}</span>
        </div>
    </div>

    {{-- STEP 1: Class Placement --}}
    <div class="step-panel active" id="step-1">
        <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 24px;">
            <div class="section-title">{{ __('Class Placement') }}</div>

            <div class="form-group">
                <label class="form-label">{{ __('Academic Session') }}</label>
                <div style="padding: 9px 12px; border: 1px solid #e2e8f0; border-radius: 7px; font-size: 0.85rem; background: #f8fafc; color: #0f172a; font-weight: 600;">
                    {{ $currentSession->name }}
                </div>
                <div style="font-size: 0.7rem; color: #94a3b8; margin-top: 3px;">{{ __('Applications are automatically submitted for the current academic session.') }}</div>
            </div>

            <div class="form-group">
                <label class="form-label">{{ __('Form / Class') }} <span class="req">*</span></label>
                <select name="form_id" id="form_id" class="form-select" required>
                    <option value="">{{ __('— Select Form —') }}</option>
                    @foreach($forms as $form)
                    <option value="{{ $form->id }}"
                            data-has-streams="{{ $form->has_streams ? '1' : '0' }}"
                            data-level="{{ $form->level }}"
                            {{ old('form_id') == $form->id ? 'selected' : '' }}>
                        {{ $form->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group" id="streamGroup" style="display: none;">
                <label class="form-label">{{ __('Stream / Specialization') }} <span class="req">*</span></label>
                <select name="stream_id" id="stream_id" class="form-select">
                    <option value="">{{ __('— Select Stream —') }}</option>
                </select>
            </div>

            <div style="background: #eff6ff; border-radius: 8px; padding: 14px; margin-top: 16px;">
                <div style="display: flex; align-items: start; gap: 10px;">
                    <svg width="20" height="20" style="color: #3b82f6; flex-shrink: 0; margin-top: 1px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div style="font-size: 0.78rem; color: #1e40af; line-height: 1.5;">
                        {{ __('Select the class you are applying for. If the form requires a stream/specialization (e.g., Science, Arts, Commercial), an additional dropdown will appear.') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- STEP 2: Personal Information --}}
    <div class="step-panel" id="step-2">
        <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 24px;">
            <div class="section-title">{{ __('Personal Information') }}</div>

            <div class="form-row" style="margin-bottom: 14px;">
                <div class="form-group">
                    <label class="form-label">{{ __('First Name') }} <span class="req">*</span></label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('Last Name') }} <span class="req">*</span></label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" class="form-input" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">{{ __('Other Names') }}</label>
                <input type="text" name="other_names" value="{{ old('other_names') }}" class="form-input">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">{{ __('Date of Birth') }} <span class="req">*</span></label>
                    <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('Gender') }} <span class="req">*</span></label>
                    <select name="gender" class="form-select" required>
                        <option value="">{{ __('— Select —') }}</option>
                        <option value="male" {{ old('gender') === 'male' ? 'selected' : '' }}>{{ __('Male') }}</option>
                        <option value="female" {{ old('gender') === 'female' ? 'selected' : '' }}>{{ __('Female') }}</option>
                    </select>
                </div>
            </div>

            <div class="form-row-3">
                <div class="form-group">
                    <label class="form-label">{{ __('Blood Group') }}</label>
                    <select name="blood_group" class="form-select">
                        <option value="">{{ __('— Select —') }}</option>
                        @foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg)
                        <option value="{{ $bg }}" {{ old('blood_group') === $bg ? 'selected' : '' }}>{{ $bg }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('Nationality') }}</label>
                    <input type="text" name="nationality" value="{{ old('nationality', 'Cameroonian') }}" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('Religion') }}</label>
                    <input type="text" name="religion" value="{{ old('religion') }}" class="form-input">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">{{ __('Place of Birth') }}</label>
                    <input type="text" name="place_of_birth" value="{{ old('place_of_birth') }}" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('Region of Origin') }}</label>
                    <input type="text" name="region_of_origin" value="{{ old('region_of_origin') }}" class="form-input">
                </div>
            </div>
        </div>
    </div>

    {{-- STEP 3: Contact & Address --}}
    <div class="step-panel" id="step-3">
        <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 24px;">
            <div class="section-title">{{ __('Contact & Address') }}</div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">{{ __('Phone Number') }}</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="form-input" placeholder="+237 6XX XXX XXX">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('Email Address') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="form-input">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">{{ __('Home Address') }}</label>
                <textarea name="home_address" class="form-input" rows="2" style="resize: vertical;">{{ old('home_address') }}</textarea>
            </div>

            <div class="form-group">
                <label class="form-label">{{ __('Town') }}</label>
                <input type="text" name="town" value="{{ old('town') }}" class="form-input">
            </div>

            <div class="section-title" style="margin-top: 24px;">{{ __('Previous School') }}</div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">{{ __('Previous School Name') }}</label>
                    <input type="text" name="previous_school" value="{{ old('previous_school') }}" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('Previous Class / Level') }}</label>
                    <input type="text" name="previous_class" value="{{ old('previous_class') }}" class="form-input">
                </div>
            </div>
        </div>
    </div>

    {{-- STEP 4: Family & Guardians --}}
    <div class="step-panel" id="step-4">
        <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 24px;">
            {{-- Father --}}
            <div class="section-title">{{ __("Father's Information") }}</div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">{{ __('Full Name') }}</label>
                    <input type="text" name="father_name" value="{{ old('father_name') }}" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('Phone') }}</label>
                    <input type="text" name="father_phone" value="{{ old('father_phone') }}" class="form-input">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">{{ __('Email') }}</label>
                    <input type="email" name="father_email" value="{{ old('father_email') }}" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('Occupation') }}</label>
                    <input type="text" name="father_occupation" value="{{ old('father_occupation') }}" class="form-input">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('Address') }}</label>
                <input type="text" name="father_address" value="{{ old('father_address') }}" class="form-input">
            </div>

            {{-- Mother --}}
            <div class="section-title" style="margin-top: 24px;">{{ __("Mother's Information") }}</div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">{{ __('Full Name') }}</label>
                    <input type="text" name="mother_name" value="{{ old('mother_name') }}" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('Phone') }}</label>
                    <input type="text" name="mother_phone" value="{{ old('mother_phone') }}" class="form-input">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">{{ __('Email') }}</label>
                    <input type="email" name="mother_email" value="{{ old('mother_email') }}" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('Occupation') }}</label>
                    <input type="text" name="mother_occupation" value="{{ old('mother_occupation') }}" class="form-input">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('Address') }}</label>
                <input type="text" name="mother_address" value="{{ old('mother_address') }}" class="form-input">
            </div>

            {{-- Guardian / Sponsor --}}
            <div class="section-title" style="margin-top: 24px;">{{ __('Guardian / Sponsor') }}</div>
            <div class="form-row-3">
                <div class="form-group">
                    <label class="form-label">{{ __('Full Name') }}</label>
                    <input type="text" name="guardian_name" value="{{ old('guardian_name') }}" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('Relationship') }}</label>
                    <input type="text" name="guardian_relationship" value="{{ old('guardian_relationship') }}" class="form-input" placeholder="e.g. Uncle, Aunt">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('Phone') }}</label>
                    <input type="text" name="guardian_phone" value="{{ old('guardian_phone') }}" class="form-input">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('Guardian Email') }}</label>
                <input type="email" name="guardian_email" value="{{ old('guardian_email') }}" class="form-input">
            </div>

            {{-- Emergency Contact --}}
            <div class="section-title" style="margin-top: 24px;">{{ __('Emergency Contact') }}</div>
            <div style="background: #fffbeb; border-radius: 8px; padding: 12px; margin-bottom: 14px;">
                <span style="font-size: 0.78rem; color: #92400e;">{{ __('Emergency contact name and phone are required.') }}</span>
            </div>
            <div class="form-row-3">
                <div class="form-group">
                    <label class="form-label">{{ __('Contact Name') }} <span class="req">*</span></label>
                    <input type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name') }}" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('Contact Phone') }} <span class="req">*</span></label>
                    <input type="text" name="emergency_contact_phone" value="{{ old('emergency_contact_phone') }}" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('Relationship') }}</label>
                    <input type="text" name="emergency_contact_relationship" value="{{ old('emergency_contact_relationship') }}" class="form-input" placeholder="e.g. Parent, Uncle">
                </div>
            </div>
        </div>
    </div>

    {{-- STEP 5: Documents --}}
    <div class="step-panel" id="step-5">
        <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 24px;">
            <div class="section-title">{{ __('Documents & Uploads') }}</div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">{{ __('Passport Photo') }}</label>
                    <div class="file-input-wrap">
                        <input type="file" name="photo_file" accept="image/*" class="form-input">
                    </div>
                    <div class="file-hint">{{ __('Max 2MB. JPG, PNG formats.') }}</div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('Birth Certificate') }}</label>
                    <div class="file-input-wrap">
                        <input type="file" name="birth_certificate_file" accept=".pdf,.jpg,.jpeg,.png" class="form-input">
                    </div>
                    <div class="file-hint">{{ __('PDF, JPG, PNG. Max 5MB.') }}</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" id="primaryCertGroup">
                    <label class="form-label">{{ __('Primary School Certificate') }}</label>
                    <div class="file-input-wrap">
                        <input type="file" name="primary_certificate_file" accept=".pdf,.jpg,.jpeg,.png" class="form-input">
                    </div>
                    <div class="file-hint">{{ __('Required for First Cycle. PDF, JPG, PNG. Max 5MB.') }}</div>
                </div>
                <div class="form-group" id="gceCertGroup" style="display: none;">
                    <label class="form-label">{{ __('GCE O/L Certificate') }} <span class="req">*</span></label>
                    <div class="file-input-wrap">
                        <input type="file" name="gce_ol_certificate_file" accept=".pdf,.jpg,.jpeg,.png" class="form-input">
                    </div>
                    <div class="file-hint">{{ __('Required for Second Cycle. PDF, JPG, PNG. Max 5MB.') }}</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">{{ __('Transfer Certificate') }}</label>
                    <div class="file-input-wrap">
                        <input type="file" name="transfer_certificate_file" accept=".pdf,.jpg,.jpeg,.png" class="form-input">
                    </div>
                    <div class="file-hint">{{ __('If transferring from another school. Max 5MB.') }}</div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('Medical Certificate') }}</label>
                    <div class="file-input-wrap">
                        <input type="file" name="medical_certificate_file" accept=".pdf,.jpg,.jpeg,.png" class="form-input">
                    </div>
                    <div class="file-hint">{{ __('Max 5MB.') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Navigation Buttons --}}
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 22px; gap: 12px;">
        <button type="button" id="prevBtn" onclick="changeStep(-1)" style="display: none; padding: 10px 22px; background: #fff; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; font-weight: 500; cursor: pointer; color: #374151; transition: all 0.2s;"
                onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#fff'">
            ← {{ __('Previous') }}
        </button>
        <div style="flex: 1;"></div>
        <button type="button" id="nextBtn" onclick="changeStep(1)" style="padding: 10px 28px; background: #0ea5e9; color: #fff; border: none; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: background 0.2s;"
                onmouseover="this.style.background='#0284c7'" onmouseout="this.style.background='#0ea5e9'">
            {{ __('Next') }} →
        </button>
        <button type="submit" id="submitBtn" style="display: none; padding: 10px 28px; background: #22c55e; color: #fff; border: none; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: background 0.2s;"
                onmouseover="this.style.background='#16a34a'" onmouseout="this.style.background='#22c55e'">
            ✓ {{ __('Submit Application') }}
        </button>
    </div>
</form>
@endsection

@push('scripts')
<script>
let currentStep = 1;
const totalSteps = 5;

function goToStep(step) {
    if (step < 1 || step > totalSteps) return;
    // Hide all panels
    document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.step-item').forEach(s => s.classList.remove('active'));
    // Show target
    document.getElementById('step-' + step).classList.add('active');
    document.querySelector('.step-item[data-step="' + step + '"]').classList.add('active');
    // Mark previous as done
    for (let i = 1; i < step; i++) {
        document.querySelector('.step-item[data-step="' + i + '"]').classList.add('done');
    }
    for (let i = step; i <= totalSteps; i++) {
        document.querySelector('.step-item[data-step="' + i + '"]').classList.remove('done');
    }
    currentStep = step;
    updateButtons();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function changeStep(dir) {
    goToStep(currentStep + dir);
}

function updateButtons() {
    document.getElementById('prevBtn').style.display = currentStep === 1 ? 'none' : 'block';
    document.getElementById('nextBtn').style.display = currentStep === totalSteps ? 'none' : 'block';
    document.getElementById('submitBtn').style.display = currentStep === totalSteps ? 'block' : 'none';
}

// Form → Streams AJAX cascade
document.getElementById('form_id').addEventListener('change', async function() {
    const formId = this.value;
    const streamGrp = document.getElementById('streamGroup');
    const streamSel = document.getElementById('stream_id');
    const gceCertGrp = document.getElementById('gceCertGroup');
    const primaryCertGrp = document.getElementById('primaryCertGroup');

    streamSel.innerHTML = '<option value="">{{ __("— Select Stream —") }}</option>';

    if (!formId) {
        streamGrp.style.display = 'none';
        gceCertGrp.style.display = 'none';
        primaryCertGrp.style.display = 'block';
        return;
    }

    const selected = this.options[this.selectedIndex];
    const hasStreams = selected.dataset.hasStreams === '1';
    const level = selected.dataset.level;

    // Toggle GCE cert section based on cycle
    if (level === 'second_cycle') {
        gceCertGrp.style.display = 'block';
        primaryCertGrp.style.display = 'none';
    } else {
        gceCertGrp.style.display = 'none';
        primaryCertGrp.style.display = 'block';
    }

    if (hasStreams) {
        streamGrp.style.display = 'block';
        try {
            const res = await fetch('{{ url("/apply/form-streams") }}/' + formId);
            const data = await res.json();
            data.forEach(stream => {
                const opt = document.createElement('option');
                opt.value = stream.id;
                opt.textContent = stream.name;
                streamSel.appendChild(opt);
            });
            // Restore old value if any
            const oldStreamId = '{{ old("stream_id", "") }}';
            if (oldStreamId) streamSel.value = oldStreamId;
        } catch (e) {
            console.error('Failed to load streams:', e);
        }
    } else {
        streamGrp.style.display = 'none';
    }
});

// Auto-navigate to step with first error on page load
document.addEventListener('DOMContentLoaded', function() {
    @if($errors->any())
    const errorKeys = @json($errors->keys());
    const stepFieldMap = {
        1: ['form_id', 'stream_id'],
        2: ['first_name', 'last_name', 'other_names', 'date_of_birth', 'gender', 'blood_group', 'nationality', 'place_of_birth', 'region_of_origin', 'religion'],
        3: ['phone', 'email', 'home_address', 'town', 'previous_school', 'previous_class'],
        4: ['father_name', 'father_phone', 'father_email', 'father_occupation', 'father_address', 'mother_name', 'mother_phone', 'mother_email', 'mother_occupation', 'mother_address', 'guardian_name', 'guardian_relationship', 'guardian_phone', 'guardian_email', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship'],
        5: ['photo_file', 'birth_certificate_file', 'primary_certificate_file', 'gce_ol_certificate_file', 'transfer_certificate_file', 'medical_certificate_file']
    };
    for (let step = 1; step <= totalSteps; step++) {
        if (errorKeys.some(k => stepFieldMap[step].includes(k))) {
            goToStep(step);
            break;
        }
    }
    @endif

    // Trigger form change to restore streams if old form_id exists
    const formSel = document.getElementById('form_id');
    if (formSel.value) {
        formSel.dispatchEvent(new Event('change'));
    }
});
</script>
@endpush
