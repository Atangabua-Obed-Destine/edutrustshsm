@extends('layouts.apply')
@section('title', __('Application') . ' ' . $application->application_number)

@section('content')
@php $badge = $application->status_badge; @endphp

<div style="margin-bottom: 20px;">
    <a href="{{ route('apply.dashboard') }}" style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.82rem; color: #64748b;"
       onmouseover="this.style.color='#0ea5e9'" onmouseout="this.style.color='#64748b'">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        {{ __('Back to Dashboard') }}
    </a>
</div>

{{-- Header --}}
<div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 22px 24px; margin-bottom: 20px; border-left: 5px solid {{ $badge['color'] }};">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div>
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                <h1 style="font-size: 1.2rem; font-weight: 700; color: #0f172a;">{{ $application->application_number }}</h1>
                <span style="background: {{ $badge['bg'] }}; color: {{ $badge['color'] }}; padding: 3px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; text-transform: uppercase;">{{ $badge['label'] }}</span>
            </div>
            <div style="font-size: 0.82rem; color: #64748b;">
                {{ __('Submitted:') }} {{ $application->created_at->format('F d, Y \a\t g:i A') }}
            </div>
        </div>
    </div>

    @if($application->status === 'rejected' && $application->admin_notes)
    <div style="margin-top: 14px; background: #fef2f2; border-radius: 8px; padding: 12px 14px;">
        <div style="font-size: 0.78rem; font-weight: 600; color: #dc2626; margin-bottom: 4px;">{{ __('Rejection Reason:') }}</div>
        <p style="font-size: 0.82rem; color: #7f1d1d;">{{ $application->admin_notes }}</p>
    </div>
    @endif

    @if($application->status === 'accepted')
    <div style="margin-top: 14px; background: #f0fdf4; border-radius: 8px; padding: 12px 14px;">
        <span style="font-size: 0.82rem; color: #166534; font-weight: 500;">✓ {{ __('Congratulations! Your application has been accepted. The school will proceed with your enrollment.') }}</span>
    </div>
    @endif

    @if($application->status === 'enrolled' && $application->student)
    <div style="margin-top: 14px; background: #eff6ff; border-radius: 8px; padding: 12px 14px;">
        <span style="font-size: 0.82rem; color: #1e40af; font-weight: 500;">🎓 {{ __('You have been enrolled! Your Student ID is:') }} <strong>{{ $application->student->student_id }}</strong></span>
    </div>
    @endif
</div>

{{-- Application Details --}}
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
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
        <table style="width: 100%; font-size: 0.82rem;">
            <tr><td style="padding: 5px 0; color: #64748b; width: 40%;">{{ __('Full Name') }}</td><td style="padding: 5px 0; color: #0f172a; font-weight: 500;">{{ $application->full_name }}</td></tr>
            <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Date of Birth') }}</td><td style="padding: 5px 0; color: #0f172a; font-weight: 500;">{{ $application->date_of_birth?->format('M d, Y') ?? '—' }}</td></tr>
            <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Gender') }}</td><td style="padding: 5px 0; color: #0f172a; font-weight: 500; text-transform: capitalize;">{{ $application->gender ?? '—' }}</td></tr>
            <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Blood Group') }}</td><td style="padding: 5px 0; color: #0f172a; font-weight: 500;">{{ $application->blood_group ?? '—' }}</td></tr>
            <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Nationality') }}</td><td style="padding: 5px 0; color: #0f172a; font-weight: 500;">{{ $application->nationality ?? '—' }}</td></tr>
            <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Place of Birth') }}</td><td style="padding: 5px 0; color: #0f172a; font-weight: 500;">{{ $application->place_of_birth ?? '—' }}</td></tr>
            <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Region of Origin') }}</td><td style="padding: 5px 0; color: #0f172a; font-weight: 500;">{{ $application->region_of_origin ?? '—' }}</td></tr>
            <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Religion') }}</td><td style="padding: 5px 0; color: #0f172a; font-weight: 500;">{{ $application->religion ?? '—' }}</td></tr>
        </table>
    </div>

    {{-- Contact --}}
    <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 20px;">
        <h3 style="font-size: 0.88rem; font-weight: 700; color: #0f172a; margin-bottom: 14px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0;">{{ __('Contact & Address') }}</h3>
        <table style="width: 100%; font-size: 0.82rem;">
            <tr><td style="padding: 5px 0; color: #64748b; width: 40%;">{{ __('Phone') }}</td><td style="padding: 5px 0; color: #0f172a; font-weight: 500;">{{ $application->phone ?? '—' }}</td></tr>
            <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Email') }}</td><td style="padding: 5px 0; color: #0f172a; font-weight: 500;">{{ $application->email ?? '—' }}</td></tr>
            <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Address') }}</td><td style="padding: 5px 0; color: #0f172a; font-weight: 500;">{{ $application->home_address ?? '—' }}</td></tr>
            <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Town') }}</td><td style="padding: 5px 0; color: #0f172a; font-weight: 500;">{{ $application->town ?? '—' }}</td></tr>
            <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Previous School') }}</td><td style="padding: 5px 0; color: #0f172a; font-weight: 500;">{{ $application->previous_school ?? '—' }}</td></tr>
            <tr><td style="padding: 5px 0; color: #64748b;">{{ __('Previous Class') }}</td><td style="padding: 5px 0; color: #0f172a; font-weight: 500;">{{ $application->previous_class ?? '—' }}</td></tr>
        </table>
    </div>

    {{-- Family --}}
    <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 20px;">
        <h3 style="font-size: 0.88rem; font-weight: 700; color: #0f172a; margin-bottom: 14px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0;">{{ __('Family Information') }}</h3>
        <table style="width: 100%; font-size: 0.82rem;">
            <tr><td colspan="2" style="padding: 8px 0 4px; color: #0ea5e9; font-weight: 600; font-size: 0.78rem;">{{ __('FATHER') }}</td></tr>
            <tr><td style="padding: 4px 0; color: #64748b; width: 40%;">{{ __('Name') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->father_name ?? '—' }}</td></tr>
            <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Phone') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->father_phone ?? '—' }}</td></tr>
            <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Occupation') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->father_occupation ?? '—' }}</td></tr>
            <tr><td colspan="2" style="padding: 8px 0 4px; color: #0ea5e9; font-weight: 600; font-size: 0.78rem;">{{ __('MOTHER') }}</td></tr>
            <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Name') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->mother_name ?? '—' }}</td></tr>
            <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Phone') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->mother_phone ?? '—' }}</td></tr>
            <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Occupation') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->mother_occupation ?? '—' }}</td></tr>
            @if($application->guardian_name)
            <tr><td colspan="2" style="padding: 8px 0 4px; color: #0ea5e9; font-weight: 600; font-size: 0.78rem;">{{ __('GUARDIAN') }}</td></tr>
            <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Name') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->guardian_name }}</td></tr>
            <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Relationship') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->guardian_relationship ?? '—' }}</td></tr>
            <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Phone') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->guardian_phone ?? '—' }}</td></tr>
            @endif
            <tr><td colspan="2" style="padding: 8px 0 4px; color: #ef4444; font-weight: 600; font-size: 0.78rem;">{{ __('EMERGENCY CONTACT') }}</td></tr>
            <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Name') }}</td><td style="padding: 4px 0; color: #0f172a; font-weight: 500;">{{ $application->emergency_contact_name }}</td></tr>
            <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Phone') }}</td><td style="padding: 4px 0; color: #0f172a; font-weight: 500;">{{ $application->emergency_contact_phone }}</td></tr>
            @if($application->emergency_contact_relationship)
            <tr><td style="padding: 4px 0; color: #64748b;">{{ __('Relationship') }}</td><td style="padding: 4px 0; color: #0f172a;">{{ $application->emergency_contact_relationship }}</td></tr>
            @endif
        </table>
    </div>
</div>

{{-- Documents --}}
@if($application->photo || $application->birth_certificate || $application->primary_certificate || $application->gce_ol_certificate || $application->transfer_certificate || $application->medical_certificate)
<div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 20px; margin-top: 20px;">
    <h3 style="font-size: 0.88rem; font-weight: 700; color: #0f172a; margin-bottom: 14px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0;">{{ __('Uploaded Documents') }}</h3>
    <div style="display: flex; flex-wrap: wrap; gap: 12px;">
        @foreach([
            'photo' => __('Passport Photo'),
            'birth_certificate' => __('Birth Certificate'),
            'primary_certificate' => __('Primary Certificate'),
            'gce_ol_certificate' => __('GCE O/L Certificate'),
            'transfer_certificate' => __('Transfer Certificate'),
            'medical_certificate' => __('Medical Certificate'),
        ] as $field => $label)
            @if($application->$field)
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; display: flex; align-items: center; gap: 8px;">
                <svg width="16" height="16" style="color: #22c55e; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span style="font-size: 0.8rem; color: #374151;">{{ $label }}</span>
            </div>
            @endif
        @endforeach
    </div>
</div>
@endif

{{-- Status Timeline --}}
<div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 20px; margin-top: 20px;">
    <h3 style="font-size: 0.88rem; font-weight: 700; color: #0f172a; margin-bottom: 14px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0;">{{ __('Application Timeline') }}</h3>
    <div style="padding-left: 20px; border-left: 2px solid #e2e8f0;">
        {{-- Submitted --}}
        <div style="position: relative; padding-bottom: 16px;">
            <div style="position: absolute; left: -27px; top: 2px; width: 14px; height: 14px; border-radius: 50%; background: #22c55e; border: 2px solid #fff;"></div>
            <div style="font-size: 0.82rem; font-weight: 600; color: #0f172a;">{{ __('Application Submitted') }}</div>
            <div style="font-size: 0.75rem; color: #94a3b8;">{{ $application->created_at->format('M d, Y \a\t g:i A') }}</div>
        </div>

        @if(in_array($application->status, ['under_review', 'accepted', 'rejected', 'enrolled']))
        <div style="position: relative; padding-bottom: 16px;">
            <div style="position: absolute; left: -27px; top: 2px; width: 14px; height: 14px; border-radius: 50%; background: #f59e0b; border: 2px solid #fff;"></div>
            <div style="font-size: 0.82rem; font-weight: 600; color: #0f172a;">{{ __('Under Review') }}</div>
            <div style="font-size: 0.75rem; color: #94a3b8;">{{ __('Your application is being reviewed by the admissions team.') }}</div>
        </div>
        @endif

        @if($application->status === 'accepted' || $application->status === 'enrolled')
        <div style="position: relative; padding-bottom: 16px;">
            <div style="position: absolute; left: -27px; top: 2px; width: 14px; height: 14px; border-radius: 50%; background: #22c55e; border: 2px solid #fff;"></div>
            <div style="font-size: 0.82rem; font-weight: 600; color: #166534;">{{ __('Accepted') }}</div>
            @if($application->reviewed_at)
            <div style="font-size: 0.75rem; color: #94a3b8;">{{ $application->reviewed_at->format('M d, Y \a\t g:i A') }}</div>
            @endif
        </div>
        @endif

        @if($application->status === 'enrolled')
        <div style="position: relative;">
            <div style="position: absolute; left: -27px; top: 2px; width: 14px; height: 14px; border-radius: 50%; background: #3b82f6; border: 2px solid #fff;"></div>
            <div style="font-size: 0.82rem; font-weight: 600; color: #1e40af;">{{ __('Enrolled') }}</div>
            <div style="font-size: 0.75rem; color: #94a3b8;">{{ __('Student ID:') }} {{ $application->student->student_id ?? '' }}</div>
        </div>
        @endif

        @if($application->status === 'rejected')
        <div style="position: relative;">
            <div style="position: absolute; left: -27px; top: 2px; width: 14px; height: 14px; border-radius: 50%; background: #ef4444; border: 2px solid #fff;"></div>
            <div style="font-size: 0.82rem; font-weight: 600; color: #dc2626;">{{ __('Rejected') }}</div>
            @if($application->reviewed_at)
            <div style="font-size: 0.75rem; color: #94a3b8;">{{ $application->reviewed_at->format('M d, Y \a\t g:i A') }}</div>
            @endif
        </div>
        @endif

        @if($application->status === 'pending')
        <div style="position: relative;">
            <div style="position: absolute; left: -27px; top: 2px; width: 14px; height: 14px; border-radius: 50%; background: #e2e8f0; border: 2px solid #fff;"></div>
            <div style="font-size: 0.82rem; color: #94a3b8;">{{ __('Awaiting review...') }}</div>
        </div>
        @endif
    </div>
</div>
@endsection
