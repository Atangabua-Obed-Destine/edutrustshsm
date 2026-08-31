@extends('layouts.admin')

@section('title', $student->full_name)
@section('breadcrumb', __('Students') . ' > ' . $student->student_id)

@section('content')
@php
    $statusColors = ['active' => 'green', 'graduated' => 'blue', 'withdrawn' => 'yellow', 'suspended' => 'red', 'expelled' => 'red'];
    $statusColor = $statusColors[$student->status] ?? 'gray';
    $enrollment = $student->currentEnrollment;
@endphp

<div class="space-y-5">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-4">
            @if($student->photo)
            <img src="{{ asset('storage/' . $student->photo) }}" alt="Photo" class="w-14 h-14 rounded-full object-cover border-2 border-gray-200">
            @else
            <div class="w-14 h-14 rounded-full bg-[#1e293b] text-white flex items-center justify-center text-lg font-bold">
                {{ strtoupper(substr($student->first_name, 0, 1) . substr($student->last_name, 0, 1)) }}
            </div>
            @endif
            <div>
                <h2 class="text-xl font-bold text-gray-800">{{ $student->full_name }}</h2>
                <div class="flex items-center gap-3 mt-0.5">
                    <span class="text-sm text-gray-500 font-mono">{{ $student->student_id }}</span>
                    <span class="inline-block px-2.5 py-0.5 text-xs font-medium rounded-full bg-{{ $statusColor }}-100 text-{{ $statusColor }}-700 capitalize">{{ $student->status }}</span>
                </div>
            </div>
        </div>
        <div class="flex gap-2">
            @can('student.view')
            <a href="{{ route('admin.documents.cumulative-record', $student) }}" target="_blank" class="bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition">{{ __('Cumulative Record') }}</a>
            {{-- Only offered once the student has actually left; the controller
                 refuses it for an active student either way. --}}
            @unless(in_array($student->status, ['active', 'suspended']))
            <a href="{{ route('admin.documents.leaving-certificate', $student) }}" target="_blank" class="bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition">{{ __('Leaving Certificate') }}</a>
            @endunless
            @endcan
            <a href="{{ route('admin.students.edit', $student) }}" class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition">{{ __('Edit') }}</a>
            <a href="{{ route('admin.students.index') }}" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition">← {{ __('Back') }}</a>
        </div>
    </div>

    {{-- ================================================================ --}}
    {{-- TOP ROW: Admission & Current Enrollment                          --}}
    {{-- ================================================================ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- Admission Info --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                Admission Details
            </h4>
            <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Student ID') }}</p>
                    <p class="font-mono font-semibold text-blue-600">{{ $student->student_id }}</p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Admission Date') }}</p>
                    <p class="font-medium">{{ $student->admission_date->format('d M Y') }}</p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Batch') }}</p>
                    <p class="font-medium">{{ $student->batch->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Status') }}</p>
                    <p class="font-medium capitalize">{{ $student->status }}</p>
                </div>
                @if($student->previous_school)
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Previous School') }}</p>
                    <p class="font-medium">{{ $student->previous_school }}</p>
                </div>
                @endif
                @if($student->previous_class)
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Previous Class') }}</p>
                    <p class="font-medium">{{ $student->previous_class }}</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Current Enrollment --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                Current Enrollment
            </h4>
            @if($enrollment)
            <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Session') }}</p>
                    <p class="font-medium">{{ $enrollment->academicSession->name }}</p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Term') }}</p>
                    <p class="font-medium">{{ $enrollment->term->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Form') }}</p>
                    <p class="font-medium">{{ $enrollment->classSection->form->name }}</p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Class / Section') }}</p>
                    <p class="font-medium">{{ $enrollment->classSection->name }}</p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Stream') }}</p>
                    <p class="font-medium">{{ $enrollment->stream->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Residence Type') }}</p>
                    <p class="font-medium capitalize">{{ str_replace('_', ' ', $enrollment->residence_type) }}</p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Enrollment Date') }}</p>
                    <p class="font-medium">{{ $enrollment->enrollment_date?->format('d M Y') ?? '—' }}</p>
                </div>
            </div>
            @else
            <p class="text-sm text-gray-400 italic">{{ __('Not enrolled in the current academic session.') }}</p>
            @endif
        </div>
    </div>

    {{-- ================================================================ --}}
    {{-- SECOND ROW: Personal + Contact                                   --}}
    {{-- ================================================================ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- Personal Information --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                Personal Information
            </h4>
            <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Full Name') }}</p>
                    <p class="font-medium">{{ $student->full_name }}</p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Gender') }}</p>
                    <p class="font-medium capitalize">{{ $student->gender }}</p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Date of Birth') }}</p>
                    <p class="font-medium">{{ $student->date_of_birth->format('d M Y') }} <span class="text-gray-400">({{ $student->age }} {{ __('yrs') }})</span></p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Blood Group') }}</p>
                    <p class="font-medium">{{ $student->blood_group ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Nationality') }}</p>
                    <p class="font-medium">{{ $student->nationality ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Place of Birth') }}</p>
                    <p class="font-medium">{{ $student->place_of_birth ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Region of Origin') }}</p>
                    <p class="font-medium">{{ $student->region_of_origin ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Religion') }}</p>
                    <p class="font-medium">{{ $student->religion ?? '—' }}</p>
                </div>
            </div>
        </div>

        {{-- Contact & Address --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                Contact & Address
            </h4>
            <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Phone') }}</p>
                    <p class="font-medium">{{ $student->phone ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Email') }}</p>
                    <p class="font-medium">{{ $student->email ?? '—' }}</p>
                </div>
                <div class="col-span-2">
                    <p class="text-gray-400 text-xs">{{ __('Home Address') }}</p>
                    <p class="font-medium">{{ $student->home_address ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">{{ __('Town') }}</p>
                    <p class="font-medium">{{ $student->town ?? '—' }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================================ --}}
    {{-- THIRD ROW: Family & Guardians (full width)                       --}}
    {{-- ================================================================ --}}
    @if($student->guardian)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
            Family & Guardians
        </h4>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
            {{-- Father --}}
            <div class="bg-gray-50 rounded-lg p-4">
                <p class="text-xs font-bold text-gray-500 uppercase mb-3">{{ __('Father') }}</p>
                @if($student->guardian->father_name)
                <div class="space-y-2 text-sm">
                    <div><p class="text-gray-400 text-xs">{{ __('Name') }}</p><p class="font-medium">{{ $student->guardian->father_name }}</p></div>
                    <div><p class="text-gray-400 text-xs">{{ __('Phone') }}</p><p class="font-medium">{{ $student->guardian->father_phone ?? '—' }}</p></div>
                    <div><p class="text-gray-400 text-xs">{{ __('Email') }}</p><p class="font-medium">{{ $student->guardian->father_email ?? '—' }}</p></div>
                    <div><p class="text-gray-400 text-xs">{{ __('Occupation') }}</p><p class="font-medium">{{ $student->guardian->father_occupation ?? '—' }}</p></div>
                    <div><p class="text-gray-400 text-xs">{{ __('Address') }}</p><p class="font-medium">{{ $student->guardian->father_address ?? '—' }}</p></div>
                </div>
                @else
                <p class="text-sm text-gray-400 italic">{{ __('Not provided') }}</p>
                @endif
            </div>

            {{-- Mother --}}
            <div class="bg-gray-50 rounded-lg p-4">
                <p class="text-xs font-bold text-gray-500 uppercase mb-3">{{ __('Mother') }}</p>
                @if($student->guardian->mother_name)
                <div class="space-y-2 text-sm">
                    <div><p class="text-gray-400 text-xs">{{ __('Name') }}</p><p class="font-medium">{{ $student->guardian->mother_name }}</p></div>
                    <div><p class="text-gray-400 text-xs">{{ __('Phone') }}</p><p class="font-medium">{{ $student->guardian->mother_phone ?? '—' }}</p></div>
                    <div><p class="text-gray-400 text-xs">{{ __('Email') }}</p><p class="font-medium">{{ $student->guardian->mother_email ?? '—' }}</p></div>
                    <div><p class="text-gray-400 text-xs">{{ __('Occupation') }}</p><p class="font-medium">{{ $student->guardian->mother_occupation ?? '—' }}</p></div>
                    <div><p class="text-gray-400 text-xs">{{ __('Address') }}</p><p class="font-medium">{{ $student->guardian->mother_address ?? '—' }}</p></div>
                </div>
                @else
                <p class="text-sm text-gray-400 italic">{{ __('Not provided') }}</p>
                @endif
            </div>

            {{-- Guardian --}}
            <div class="bg-gray-50 rounded-lg p-4">
                <p class="text-xs font-bold text-gray-500 uppercase mb-3">{{ __('Guardian') }}</p>
                @if($student->guardian->guardian_name)
                <div class="space-y-2 text-sm">
                    <div><p class="text-gray-400 text-xs">{{ __('Name') }}</p><p class="font-medium">{{ $student->guardian->guardian_name }}</p></div>
                    <div><p class="text-gray-400 text-xs">{{ __('Relationship') }}</p><p class="font-medium capitalize">{{ $student->guardian->guardian_relationship ?? '—' }}</p></div>
                    <div><p class="text-gray-400 text-xs">{{ __('Phone') }}</p><p class="font-medium">{{ $student->guardian->guardian_phone ?? '—' }}</p></div>
                    <div><p class="text-gray-400 text-xs">{{ __('Email') }}</p><p class="font-medium">{{ $student->guardian->guardian_email ?? '—' }}</p></div>
                </div>
                @else
                <p class="text-sm text-gray-400 italic">{{ __('Not provided') }}</p>
                @endif
            </div>

            {{-- Emergency Contact --}}
            <div class="bg-red-50 rounded-lg p-4 border border-red-100">
                <p class="text-xs font-bold text-red-500 uppercase mb-3">{{ __('Emergency Contact') }}</p>
                <div class="space-y-2 text-sm">
                    <div><p class="text-gray-400 text-xs">{{ __('Name') }}</p><p class="font-medium">{{ $student->guardian->emergency_contact_name }}</p></div>
                    <div><p class="text-gray-400 text-xs">{{ __('Phone') }}</p><p class="font-medium">{{ $student->guardian->emergency_contact_phone }}</p></div>
                    <div><p class="text-gray-400 text-xs">{{ __('Relationship') }}</p><p class="font-medium capitalize">{{ $student->guardian->emergency_contact_relationship ?? '—' }}</p></div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ================================================================ --}}
    {{-- FOURTH ROW: Documents                                            --}}
    {{-- ================================================================ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
            Documents & Uploads
        </h4>
        @php
            $documents = [
                ['label' => __('Passport Photo'), 'field' => $student->photo, 'isImage' => true],
                ['label' => __('Birth Certificate'), 'field' => $student->birth_certificate, 'isImage' => false],
                ['label' => __('Primary School Certificate'), 'field' => $student->primary_certificate, 'isImage' => false],
                ['label' => __('GCE O Level Certificate'), 'field' => $student->gce_ol_certificate, 'isImage' => false],
                ['label' => __('Transfer Certificate'), 'field' => $student->transfer_certificate, 'isImage' => false],
                ['label' => __('Medical Certificate'), 'field' => $student->medical_certificate, 'isImage' => false],
            ];
        @endphp
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4">
            @foreach($documents as $doc)
            <div class="text-center">
                @if($doc['field'])
                    @if($doc['isImage'])
                    <a href="{{ asset('storage/' . $doc['field']) }}" target="_blank" class="block group">
                        <div class="w-full aspect-square rounded-lg border-2 border-gray-200 group-hover:border-blue-400 overflow-hidden transition">
                            <img src="{{ asset('storage/' . $doc['field']) }}" alt="{{ $doc['label'] }}" class="w-full h-full object-cover">
                        </div>
                    </a>
                    @else
                    <a href="{{ asset('storage/' . $doc['field']) }}" target="_blank" class="block group">
                        <div class="w-full aspect-square rounded-lg border-2 border-gray-200 group-hover:border-blue-400 bg-gray-50 flex flex-col items-center justify-center transition">
                            <svg class="w-8 h-8 text-green-500 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <span class="text-xs text-blue-600 group-hover:underline">{{ __('View') }}</span>
                        </div>
                    </a>
                    @endif
                @else
                <div class="w-full aspect-square rounded-lg border-2 border-dashed border-gray-200 bg-gray-50 flex flex-col items-center justify-center">
                    <svg class="w-8 h-8 text-gray-300 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    <span class="text-xs text-gray-400">{{ __('Not uploaded') }}</span>
                </div>
                @endif
                <p class="text-xs text-gray-500 mt-1.5 leading-tight">{{ $doc['label'] }}</p>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ================================================================ --}}
    {{-- FIFTH ROW: Enrollment History + Subjects                         --}}
    {{-- ================================================================ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Enrollment History --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                Enrollment History
            </h4>
            @if($student->enrollments->count())
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                        <tr>
                            <th class="px-3 py-2 text-left">{{ __('Session') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('Term') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('Class') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('Stream') }}</th>
                            <th class="px-3 py-2 text-center">{{ __('Status') }}</th>
                            <th class="px-3 py-2 text-center">{{ __('Average') }}</th>
                            <th class="px-3 py-2 text-center">{{ __('Rank') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($student->enrollments as $e)
                        <tr class="{{ $e->id === $enrollment?->id ? 'bg-blue-50/50' : '' }}">
                            <td class="px-3 py-2">{{ $e->academicSession->name }}</td>
                            <td class="px-3 py-2">{{ $e->term->name ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $e->classSection->name }}</td>
                            <td class="px-3 py-2">{{ $e->stream->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-center">
                                <span class="px-2 py-0.5 text-xs rounded-full capitalize
                                    {{ $e->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $e->status }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-center font-medium">{{ $e->final_average ?? '—' }}</td>
                            <td class="px-3 py-2 text-center font-medium">{{ $e->final_rank ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p class="text-sm text-gray-400 italic">{{ __('No enrollment history.') }}</p>
            @endif
        </div>

        {{-- Enrolled Subjects --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                Enrolled Subjects
            </h4>
            @if($enrollment?->studentSubjects?->count())
            <div class="space-y-2">
                @foreach($enrollment->studentSubjects as $ss)
                <div class="flex items-center justify-between py-1.5 px-3 bg-gray-50 rounded-lg">
                    <span class="text-sm font-medium text-gray-700">{{ $ss->subject->name }}</span>
                    <span class="text-xs font-bold text-blue-600 bg-blue-100 px-2 py-0.5 rounded-full">x{{ $ss->coefficient }}</span>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-sm text-gray-400 italic">{{ __('No subjects assigned yet.') }}</p>
            @endif
        </div>
    </div>
</div>
@endsection
