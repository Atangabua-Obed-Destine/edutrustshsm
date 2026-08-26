@extends('layouts.admin')
@section('title', __('Review Application') . ' - ' . $application->application_number)

@section('content')
<div style="padding: 24px;">
    @php $badge = $application->status_badge; @endphp

    {{-- Back Link --}}
    <div style="margin-bottom: 16px;">
        <a href="{{ route('admin.admissions.applications.index') }}" style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.82rem; color: #64748b;"
           onmouseover="this.style.color='#0ea5e9'" onmouseout="this.style.color='#64748b'">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            {{ __('Back to Applications') }}
        </a>
    </div>

    {{-- Header --}}
    <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 22px 24px; margin-bottom: 20px; border-left: 5px solid {{ $badge['color'] }};">
        <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                    <h1 style="font-size: 1.25rem; font-weight: 700; color: #0f172a;">{{ $application->application_number }}</h1>
                    <span style="background: {{ $badge['bg'] }}; color: {{ $badge['color'] }}; padding: 3px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; text-transform: uppercase;">{{ $badge['label'] }}</span>
                </div>
                <div style="font-size: 0.85rem; color: #0f172a; font-weight: 500;">{{ $application->full_name }}</div>
                <div style="font-size: 0.78rem; color: #64748b; margin-top: 3px;">
                    {{ __('Session:') }} <strong style="color: #0f172a;">{{ $application->academicSession->name ?? '—' }}</strong>
                    · {{ __('Applied:') }} {{ $application->created_at->format('F d, Y \a\t g:i A') }}
                    @if($application->reviewer)
                    · {{ __('Reviewed by:') }} {{ $application->reviewer->full_name }}
                    @endif
                </div>
            </div>

            {{-- Action Buttons --}}
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                @if(in_array($application->status, ['pending']))
                <form method="POST" action="{{ route('admin.admissions.applications.update-status', $application) }}" style="display:inline;">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="under_review">
                    <button type="submit" style="padding: 8px 16px; background: #3b82f6; color: #fff; border: none; border-radius: 7px; font-size: 0.78rem; font-weight: 600; cursor: pointer;"
                            onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#3b82f6'">
                        {{ __('Mark Under Review') }}
                    </button>
                </form>
                @endif

                @if(in_array($application->status, ['pending', 'under_review']))
                <button type="button" onclick="document.getElementById('acceptModal').style.display='flex'" style="padding: 8px 16px; background: #22c55e; color: #fff; border: none; border-radius: 7px; font-size: 0.78rem; font-weight: 600; cursor: pointer;"
                        onmouseover="this.style.background='#16a34a'" onmouseout="this.style.background='#22c55e'">
                    ✓ {{ __('Accept') }}
                </button>
                <button type="button" onclick="document.getElementById('rejectModal').style.display='flex'" style="padding: 8px 16px; background: #ef4444; color: #fff; border: none; border-radius: 7px; font-size: 0.78rem; font-weight: 600; cursor: pointer;"
                        onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#ef4444'">
                    ✕ {{ __('Reject') }}
                </button>
                @endif

                @if($application->status === 'accepted')
                <button type="button" onclick="document.getElementById('enrolSection').scrollIntoView({behavior:'smooth'})" style="padding: 8px 16px; background: #8b5cf6; color: #fff; border: none; border-radius: 7px; font-size: 0.78rem; font-weight: 600; cursor: pointer;"
                        onmouseover="this.style.background='#7c3aed'" onmouseout="this.style.background='#8b5cf6'">
                    🎓 {{ __('Enrol Student') }}
                </button>
                @endif
            </div>
        </div>

        @if($application->admin_notes)
        <div style="margin-top: 14px; background: #f8fafc; border-radius: 8px; padding: 12px 14px; border-left: 3px solid #94a3b8;">
            <div style="font-size: 0.72rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">{{ __('Admin Notes') }}</div>
            <p style="font-size: 0.82rem; color: #374151;">{{ $application->admin_notes }}</p>
        </div>
        @endif
    </div>

    {{-- Application Details Grid --}}
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
        {{-- Class Placement --}}
        <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 20px;">
            <h3 style="font-size: 0.88rem; font-weight: 700; color: #0f172a; margin-bottom: 14px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0;">{{ __('Class Placement') }}</h3>
            <table style="width: 100%; font-size: 0.82rem;">
                <tr><td style="padding: 5px 0; color: #64748b; width: 40%;">{{ __('Session') }}</td><td style="padding: 5px 0; color: #0f172a; font-weight: 500;">{{ $application->academicSession->name ?? '—' }}</td></tr>
                <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Form') }}</td><td style="padding: 5px 0; color: #0f172a; font-weight: 500;">{{ $application->form->name ?? '—' }}</td></tr>
                @if($application->stream)
                <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Stream') }}</td><td style="padding: 5px 0; color: #0f172a; font-weight: 500;">{{ $application->stream->name }}</td></tr>
                @endif
            </table>
        </div>

        {{-- Personal Info --}}
        <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 20px;">
            <h3 style="font-size: 0.88rem; font-weight: 700; color: #0f172a; margin-bottom: 14px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0;">{{ __('Personal Information') }}</h3>
            @if($application->photo)
            <div style="text-align: center; margin-bottom: 12px;">
                <img src="{{ asset('storage/' . $application->photo) }}" alt="Photo" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #e2e8f0;">
            </div>
            @endif
            <table style="width: 100%; font-size: 0.82rem;">
                <tr><td style="padding: 5px 0; color: #64748b; width: 40%;">{{ __('Full Name') }}</td><td style="padding: 5px 0; color: #0f172a; font-weight: 500;">{{ $application->full_name }}</td></tr>
                <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Date of Birth') }}</td><td style="padding: 5px 0; color: #0f172a;">{{ $application->date_of_birth?->format('M d, Y') ?? '—' }}</td></tr>
                <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Gender') }}</td><td style="padding: 5px 0; color: #0f172a; text-transform: capitalize;">{{ $application->gender ?? '—' }}</td></tr>
                <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Blood Group') }}</td><td style="padding: 5px 0; color: #0f172a;">{{ $application->blood_group ?? '—' }}</td></tr>
                <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Nationality') }}</td><td style="padding: 5px 0; color: #0f172a;">{{ $application->nationality ?? '—' }}</td></tr>
                <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Place of Birth') }}</td><td style="padding: 5px 0; color: #0f172a;">{{ $application->place_of_birth ?? '—' }}</td></tr>
                <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Region') }}</td><td style="padding: 5px 0; color: #0f172a;">{{ $application->region_of_origin ?? '—' }}</td></tr>
                <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Religion') }}</td><td style="padding: 5px 0; color: #0f172a;">{{ $application->religion ?? '—' }}</td></tr>
            </table>
        </div>

        {{-- Contact --}}
        <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 20px;">
            <h3 style="font-size: 0.88rem; font-weight: 700; color: #0f172a; margin-bottom: 14px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0;">{{ __('Contact & Address') }}</h3>
            <table style="width: 100%; font-size: 0.82rem;">
                <tr><td style="padding: 5px 0; color: #64748b; width: 40%;">{{ __('Phone') }}</td><td style="padding: 5px 0; color: #0f172a;">{{ $application->phone ?? '—' }}</td></tr>
                <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Email') }}</td><td style="padding: 5px 0; color: #0f172a;">{{ $application->email ?? '—' }}</td></tr>
                <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Address') }}</td><td style="padding: 5px 0; color: #0f172a;">{{ $application->home_address ?? '—' }}</td></tr>
                <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Town') }}</td><td style="padding: 5px 0; color: #0f172a;">{{ $application->town ?? '—' }}</td></tr>
                <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Previous School') }}</td><td style="padding: 5px 0; color: #0f172a;">{{ $application->previous_school ?? '—' }}</td></tr>
                <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Previous Class') }}</td><td style="padding: 5px 0; color: #0f172a;">{{ $application->previous_class ?? '—' }}</td></tr>
            </table>
        </div>

        {{-- Family --}}
        <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 20px;">
            <h3 style="font-size: 0.88rem; font-weight: 700; color: #0f172a; margin-bottom: 14px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0;">{{ __('Family Information') }}</h3>
            <table style="width: 100%; font-size: 0.82rem;">
                <tr><td colspan="2" style="padding: 6px 0 3px; color: #0ea5e9; font-weight: 600; font-size: 0.75rem;">{{ __('FATHER') }}</td></tr>
                <tr><td style="padding: 4px 0; color: #64748b; width: 40%;">{{ __('Name') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->father_name ?? '—' }}</td></tr>
                <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Phone') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->father_phone ?? '—' }}</td></tr>
                <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Email') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->father_email ?? '—' }}</td></tr>
                <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Occupation') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->father_occupation ?? '—' }}</td></tr>
                <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Address') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->father_address ?? '—' }}</td></tr>

                <tr><td colspan="2" style="padding: 8px 0 3px; color: #0ea5e9; font-weight: 600; font-size: 0.75rem;">{{ __('MOTHER') }}</td></tr>
                <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Name') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->mother_name ?? '—' }}</td></tr>
                <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Phone') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->mother_phone ?? '—' }}</td></tr>
                <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Email') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->mother_email ?? '—' }}</td></tr>
                <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Occupation') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->mother_occupation ?? '—' }}</td></tr>
                <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Address') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->mother_address ?? '—' }}</td></tr>

                @if($application->guardian_name)
                <tr><td colspan="2" style="padding: 8px 0 3px; color: #0ea5e9; font-weight: 600; font-size: 0.75rem;">{{ __('GUARDIAN') }}</td></tr>
                <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Name') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->guardian_name }}</td></tr>
                <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Relationship') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->guardian_relationship ?? '—' }}</td></tr>
                <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Phone') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->guardian_phone ?? '—' }}</td></tr>
                <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Email') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->guardian_email ?? '—' }}</td></tr>
                @endif

                <tr><td colspan="2" style="padding: 8px 0 3px; color: #ef4444; font-weight: 600; font-size: 0.75rem;">{{ __('EMERGENCY CONTACT') }}</td></tr>
                <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Name') }}</td><td style="padding: 4px 0; color: #0f172a; font-weight: 500;">{{ $application->emergency_contact_name }}</td></tr>
                <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Phone') }}</td><td style="padding: 4px 0; color: #0f172a; font-weight: 500;">{{ $application->emergency_contact_phone }}</td></tr>
                <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Relationship') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->emergency_contact_relationship ?? '—' }}</td></tr>
            </table>
        </div>
    </div>

    {{-- Documents --}}
    @php
    $docs = [
        'photo' => __('Passport Photo'),
        'birth_certificate' => __('Birth Certificate'),
        'primary_certificate' => __('Primary Certificate'),
        'gce_ol_certificate' => __('GCE O/L Certificate'),
        'transfer_certificate' => __('Transfer Certificate'),
        'medical_certificate' => __('Medical Certificate'),
    ];
    $hasAnyDoc = false;
    foreach ($docs as $f => $l) { if ($application->$f) { $hasAnyDoc = true; break; } }
    @endphp
    @if($hasAnyDoc)
    <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 20px; margin-bottom: 20px;">
        <h3 style="font-size: 0.88rem; font-weight: 700; color: #0f172a; margin-bottom: 14px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0;">{{ __('Uploaded Documents') }}</h3>
        <div style="display: flex; flex-wrap: wrap; gap: 12px;">
            @foreach($docs as $field => $label)
                @if($application->$field)
                <a href="{{ asset('storage/' . $application->$field) }}" target="_blank"
                   style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; display: flex; align-items: center; gap: 8px; transition: all 0.2s;"
                   onmouseover="this.style.borderColor='#0ea5e9'; this.style.background='#eff6ff'" onmouseout="this.style.borderColor='#e2e8f0'; this.style.background='#f8fafc'">
                    <svg width="16" height="16" style="color: #22c55e; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span style="font-size: 0.8rem; color: #374151; font-weight: 500;">{{ $label }}</span>
                    <svg width="12" height="12" style="color: #94a3b8;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
                @endif
            @endforeach
        </div>
    </div>
    @endif

    {{-- Enrollment Section (only for accepted) --}}
    @if($application->status === 'accepted')
    <div id="enrolSection" style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 24px; margin-bottom: 20px; border: 2px solid #8b5cf6;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 2px solid #f3e8ff;">
            <svg width="24" height="24" style="color: #8b5cf6;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
            <div>
                <h3 style="font-size: 1rem; font-weight: 700; color: #0f172a;">{{ __('Enrol as Student') }}</h3>
                <p style="font-size: 0.78rem; color: #64748b;">{{ __('Review class placement and complete enrollment details to create the student record.') }}</p>
            </div>
        </div>

        @if($errors->any())
        <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px; margin-bottom: 16px;">
            <ul style="list-style: none; padding: 0; margin: 0;">
                @foreach($errors->all() as $error)
                <li style="font-size: 0.78rem; color: #dc2626; padding: 2px 0;">• {{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('admin.admissions.applications.enrol', $application) }}">
            @csrf

            {{-- Class Placement (editable) --}}
            <div style="background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 10px; padding: 16px 18px; margin-bottom: 18px;">
                <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 12px;">
                    <svg width="16" height="16" style="color: #8b5cf6;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    <span style="font-size: 0.78rem; font-weight: 700; color: #6b21a8; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Class Placement') }}</span>
                    <span style="font-size: 0.68rem; color: #9333ea; margin-left: 4px;">{{ __('(editable — change if needed)') }}</span>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px;">
                    <div>
                        <label style="display: block; font-size: 0.78rem; font-weight: 600; color: #374151; margin-bottom: 5px;">{{ __('Form / Class') }} <span style="color: #ef4444;">*</span></label>
                        <select name="form_id" id="enrolFormSelect" required style="width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 7px; font-size: 0.85rem; outline: none; background: #fff;">
                            @foreach($forms as $form)
                            <option value="{{ $form->id }}" data-has-streams="{{ $form->has_streams ? '1' : '0' }}" {{ old('form_id', $application->form_id) == $form->id ? 'selected' : '' }}>{{ $form->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div id="enrolStreamGroup" style="{{ ($application->form && $application->form->has_streams) ? '' : 'display:none;' }}">
                        <label style="display: block; font-size: 0.78rem; font-weight: 600; color: #374151; margin-bottom: 5px;">{{ __('Stream') }} <span style="color: #ef4444;">*</span></label>
                        <select name="stream_id" id="enrolStreamSelect" style="width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 7px; font-size: 0.85rem; outline: none; background: #fff;">
                            <option value="">{{ __('— Select Stream —') }}</option>
                            @foreach($streams as $stream)
                            <option value="{{ $stream->id }}" {{ old('stream_id', $application->stream_id) == $stream->id ? 'selected' : '' }}>{{ $stream->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.78rem; font-weight: 600; color: #374151; margin-bottom: 5px;">{{ __('Section') }} <span style="color: #ef4444;">*</span></label>
                        <select name="class_section_id" id="enrolSectionSelect" required style="width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 7px; font-size: 0.85rem; outline: none; background: #fff;">
                            <option value="">{{ __('— Select Section —') }}</option>
                            @foreach($classSections as $section)
                            <option value="{{ $section->id }}" {{ old('class_section_id') == $section->id ? 'selected' : '' }}>{{ $section->name }}</option>
                            @endforeach
                        </select>
                        <span id="enrolSectionLoading" style="display: none; font-size: 0.72rem; color: #8b5cf6; margin-top: 4px;">{{ __('Loading sections...') }}</span>
                    </div>
                </div>
            </div>

            {{-- Enrollment Details --}}
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px;">
                <div>
                    <label style="display: block; font-size: 0.78rem; font-weight: 600; color: #374151; margin-bottom: 5px;">{{ __('Batch / Year') }} <span style="color: #ef4444;">*</span></label>
                    <select name="batch_id" required style="width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 7px; font-size: 0.85rem; outline: none;">
                        <option value="">{{ __('— Select —') }}</option>
                        @foreach($batches as $batch)
                        <option value="{{ $batch->id }}" {{ old('batch_id') == $batch->id ? 'selected' : '' }}>{{ $batch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 0.78rem; font-weight: 600; color: #374151; margin-bottom: 5px;">{{ __('Term') }} <span style="color: #ef4444;">*</span></label>
                    <select name="term_id" required style="width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 7px; font-size: 0.85rem; outline: none;">
                        <option value="">{{ __('— Select —') }}</option>
                        @foreach($terms as $term)
                        <option value="{{ $term->id }}" {{ old('term_id') == $term->id ? 'selected' : '' }}>{{ $term->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 0.78rem; font-weight: 600; color: #374151; margin-bottom: 5px;">{{ __('Residence Type') }} <span style="color: #ef4444;">*</span></label>
                    <select name="residence_type" required style="width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 7px; font-size: 0.85rem; outline: none;">
                        <option value="">{{ __('— Select —') }}</option>
                        <option value="day" {{ old('residence_type') === 'day' ? 'selected' : '' }}>{{ __('Day') }}</option>
                        <option value="boarding" {{ old('residence_type') === 'boarding' ? 'selected' : '' }}>{{ __('Boarding') }}</option>
                        <option value="half_boarding" {{ old('residence_type') === 'half_boarding' ? 'selected' : '' }}>{{ __('Half Boarding') }}</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 0.78rem; font-weight: 600; color: #374151; margin-bottom: 5px;">{{ __('Admission Date') }} <span style="color: #ef4444;">*</span></label>
                    <input type="date" name="admission_date" value="{{ old('admission_date', date('Y-m-d')) }}" required
                           style="width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 7px; font-size: 0.85rem; outline: none;">
                </div>
            </div>
            <div style="margin-top: 18px; text-align: right;">
                <button type="submit" style="padding: 10px 24px; background: #8b5cf6; color: #fff; border: none; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: background 0.2s;"
                        onmouseover="this.style.background='#7c3aed'" onmouseout="this.style.background='#8b5cf6'"
                        onclick="return confirm('{{ __('Enrol this applicant as a student? This will create a student record and enrollment.') }}')">
                    🎓 {{ __('Confirm Enrollment') }}
                </button>
            </div>
        </form>
    </div>

    <script>
    (function() {
        var formSelect = document.getElementById('enrolFormSelect');
        var streamGroup = document.getElementById('enrolStreamGroup');
        var streamSelect = document.getElementById('enrolStreamSelect');
        var sectionSelect = document.getElementById('enrolSectionSelect');
        var sectionLoading = document.getElementById('enrolSectionLoading');
        var basePath = '{{ url("admin/admissions") }}';

        formSelect.addEventListener('change', function() {
            var formId = this.value;
            var opt = this.options[this.selectedIndex];
            var hasStreams = opt.getAttribute('data-has-streams') === '1';

            // Update streams
            if (hasStreams) {
                streamGroup.style.display = '';
                streamSelect.innerHTML = '<option value="">{{ __("— Loading... —") }}</option>';
                fetch(basePath + '/streams-by-form/' + formId)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        streamSelect.innerHTML = '<option value="">{{ __("— Select Stream —") }}</option>';
                        data.forEach(function(s) {
                            var o = document.createElement('option');
                            o.value = s.id;
                            o.textContent = s.name;
                            streamSelect.appendChild(o);
                        });
                    });
            } else {
                streamGroup.style.display = 'none';
                streamSelect.innerHTML = '<option value="">{{ __("— Select Stream —") }}</option>';
            }

            // Update sections
            sectionSelect.innerHTML = '<option value="">{{ __("— Loading... —") }}</option>';
            sectionLoading.style.display = 'block';
            fetch(basePath + '/sections-by-form/' + formId)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    sectionLoading.style.display = 'none';
                    sectionSelect.innerHTML = '<option value="">{{ __("— Select Section —") }}</option>';
                    data.forEach(function(s) {
                        var o = document.createElement('option');
                        o.value = s.id;
                        o.textContent = s.name;
                        sectionSelect.appendChild(o);
                    });
                    if (data.length === 0) {
                        sectionSelect.innerHTML = '<option value="">{{ __("— No sections found —") }}</option>';
                    }
                });
        });
    })();
    </script>
    @endif

    {{-- Enrolled Info --}}
    @if($application->status === 'enrolled' && $application->student)
    <div style="background: #f0fdf4; border: 2px solid #86efac; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
            <svg width="24" height="24" style="color: #22c55e;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <h3 style="font-size: 1rem; font-weight: 700; color: #166534;">{{ __('Student Enrolled Successfully') }}</h3>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 0.82rem;">
            <div><span style="color: #64748b;">{{ __('Student ID:') }}</span> <strong style="color: #166534;">{{ $application->student->student_id }}</strong></div>
            <div><span style="color: #64748b;">{{ __('Full Name:') }}</span> <strong style="color: #0f172a;">{{ $application->student->full_name }}</strong></div>
        </div>
    </div>
    @endif
</div>

{{-- Accept Modal --}}
<div id="acceptModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center;" onclick="if(event.target===this) this.style.display='none'">
    <div style="background: #fff; border-radius: 14px; padding: 28px; max-width: 440px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <h3 style="font-size: 1.05rem; font-weight: 700; color: #166534; margin-bottom: 6px;">{{ __('Accept Application') }}</h3>
        <p style="font-size: 0.82rem; color: #64748b; margin-bottom: 16px;">{{ __('This will mark the application as accepted. You can then proceed to enrol the student.') }}</p>
        <form method="POST" action="{{ route('admin.admissions.applications.accept', $application) }}">
            @csrf
            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 0.78rem; font-weight: 600; color: #374151; margin-bottom: 5px;">{{ __('Notes (optional)') }}</label>
                <textarea name="admin_notes" rows="3" style="width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 7px; font-size: 0.85rem; outline: none; resize: vertical; font-family: inherit;">{{ $application->admin_notes }}</textarea>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('acceptModal').style.display='none'" style="padding: 8px 16px; background: #f1f5f9; color: #64748b; border: none; border-radius: 7px; font-size: 0.82rem; cursor: pointer;">{{ __('Cancel') }}</button>
                <button type="submit" style="padding: 8px 20px; background: #22c55e; color: #fff; border: none; border-radius: 7px; font-size: 0.82rem; font-weight: 600; cursor: pointer;">✓ {{ __('Accept') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Reject Modal --}}
<div id="rejectModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center;" onclick="if(event.target===this) this.style.display='none'">
    <div style="background: #fff; border-radius: 14px; padding: 28px; max-width: 440px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <h3 style="font-size: 1.05rem; font-weight: 700; color: #dc2626; margin-bottom: 6px;">{{ __('Reject Application') }}</h3>
        <p style="font-size: 0.82rem; color: #64748b; margin-bottom: 16px;">{{ __('Please provide a reason for rejection. This will be visible to the applicant.') }}</p>
        <form method="POST" action="{{ route('admin.admissions.applications.reject', $application) }}">
            @csrf
            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 0.78rem; font-weight: 600; color: #374151; margin-bottom: 5px;">{{ __('Rejection Reason') }} <span style="color: #ef4444;">*</span></label>
                <textarea name="admin_notes" rows="3" required placeholder="{{ __('Please explain the reason for rejection...') }}" style="width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 7px; font-size: 0.85rem; outline: none; resize: vertical; font-family: inherit;"></textarea>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('rejectModal').style.display='none'" style="padding: 8px 16px; background: #f1f5f9; color: #64748b; border: none; border-radius: 7px; font-size: 0.82rem; cursor: pointer;">{{ __('Cancel') }}</button>
                <button type="submit" style="padding: 8px 20px; background: #ef4444; color: #fff; border: none; border-radius: 7px; font-size: 0.82rem; font-weight: 600; cursor: pointer;">✕ {{ __('Reject') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
