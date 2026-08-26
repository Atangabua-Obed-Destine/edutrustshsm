@extends('layouts.admin')
@section('title', __('Bulk Student Upload'))

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">{{ __('Bulk Student Upload') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('Import multiple students via CSV file') }}</p>
        </div>
        <a href="{{ route('admin.students.index') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-600 hover:text-gray-800">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            {{ __('Back to Students') }}
        </a>
    </div>

    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-emerald-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-sm text-emerald-700 font-medium">{{ session('success') }}</p>
        </div>
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-sm text-red-700 font-medium">{{ session('error') }}</p>
        </div>
    </div>
    @endif

    @if(session('upload_errors'))
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
            <div>
                <p class="text-sm font-medium text-amber-700 mb-2">{{ __('Some rows had errors:') }}</p>
                <ul class="text-xs text-amber-600 space-y-1 max-h-40 overflow-y-auto">
                    @foreach(session('upload_errors') as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    @endif

    @if(!$currentSession)
    <div class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
        <svg class="w-12 h-12 text-red-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <h3 class="text-base font-semibold text-red-700">{{ __('No Active Session') }}</h3>
        <p class="text-sm text-red-600 mt-1">{{ __('Please create and activate an academic session before uploading students.') }}</p>
    </div>
    @else

    {{-- Step 1: Download Template --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="bg-gradient-to-r from-slate-700 to-slate-800 px-6 py-4">
            <div class="flex items-center gap-3">
                <span class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-white font-bold text-sm">1</span>
                <h2 class="text-white font-semibold">{{ __('Download Template') }}</h2>
            </div>
        </div>
        <div class="p-6">
            <p class="text-sm text-gray-600 mb-4">
                {{ __('Download the CSV template below. Fill it in with student data, one row per student. Required fields:') }}
                <span class="font-medium text-gray-800">first_name, last_name, date_of_birth, gender, emergency_contact_name, emergency_contact_phone</span>.
            </p>
            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">{{ __('Template Columns') }}</h4>
                <div class="flex flex-wrap gap-1.5">
                    @foreach(['first_name*', 'last_name*', 'other_names', 'date_of_birth*', 'gender*', 'nationality', 'place_of_birth', 'religion', 'residence_type', 'phone', 'email', 'home_address', 'father_name', 'father_phone', 'mother_name', 'mother_phone', 'emergency_contact_name*', 'emergency_contact_phone*', 'previous_school', 'previous_class'] as $col)
                        <span class="inline-block text-xs px-2 py-0.5 rounded {{ str_contains($col, '*') ? 'bg-red-100 text-red-700 font-medium' : 'bg-gray-200 text-gray-600' }}">{{ str_replace('*', '', $col) }}{{ str_contains($col, '*') ? ' *' : '' }}</span>
                    @endforeach
                </div>
                <p class="text-xs text-gray-400 mt-2">{{ __('* = required fields') }}</p>
            </div>
            <a href="{{ route('admin.bulk-upload.template') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 text-sm font-medium transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                {{ __('Download CSV Template') }}
            </a>
        </div>
    </div>

    {{-- Step 2: Upload --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="bg-gradient-to-r from-slate-700 to-slate-800 px-6 py-4">
            <div class="flex items-center gap-3">
                <span class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-white font-bold text-sm">2</span>
                <h2 class="text-white font-semibold">{{ __('Upload Filled CSV') }}</h2>
            </div>
        </div>
        <div class="p-6">
            <form action="{{ route('admin.bulk-upload.upload') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Target Class') }} <span class="text-red-500">*</span></label>
                    <select name="class_section_id" required class="w-full rounded-lg border-gray-300 shadow-sm text-sm">
                        <option value="">{{ __('Select class to enroll students into...') }}</option>
                        @foreach($classSections as $cs)
                            <option value="{{ $cs->id }}">{{ $cs->name }} ({{ $cs->form->name }})</option>
                        @endforeach
                    </select>
                    @error('class_section_id')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('CSV File') }} <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="file" name="csv_file" accept=".csv,.txt" required
                               class="w-full rounded-lg border-gray-300 shadow-sm text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
                    </div>
                    <p class="text-xs text-gray-400 mt-1">{{ __('Max file size: 5MB. Only .csv files.') }}</p>
                    @error('csv_file')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                    <div class="flex items-start gap-2">
                        <svg class="w-4 h-4 text-amber-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div class="text-xs text-amber-700">
                            <p class="font-medium mb-0.5">{{ __('Important Notes:') }}</p>
                            <ul class="list-disc list-inside space-y-0.5 text-amber-600">
                                <li>{{ __('Gender must be') }} <strong>male</strong> {{ __('or') }} <strong>female</strong> ({{ __('lowercase') }})</li>
                                <li>{{ __('Date of birth format:') }} <strong>YYYY-MM-DD</strong></li>
                                <li>{{ __('Residence type:') }} <strong>day</strong>, <strong>boarding</strong>, {{ __('or') }} <strong>half_boarding</strong></li>
                                <li>{{ __('Student IDs will be auto-generated') }} ({{ __('e.g.') }}, {{ \App\Models\SchoolSetting::current()->student_id_prefix ?? 'SCH' }}/{{ date('Y') }}/001)</li>
                                <li>{{ __('Session:') }} <strong>{{ $currentSession->name }}</strong></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    {{ __('Upload & Import Students') }}
                </button>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection
