@php $__school = \App\Models\SchoolSetting::current(); @endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - {{ $__school->school_name ?? 'EduTrustSchool' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .sidebar-link.active, .sidebar-link:hover { background-color: rgba(255,255,255,0.1); }
        .sidebar-submenu { max-height: 0; overflow: hidden; transition: max-height 0.3s ease; }
        .sidebar-submenu.open { max-height: 500px; }
        .sidebar-chevron { transition: transform 0.3s ease; }
        .sidebar-chevron.rotated { transform: rotate(90deg); }
        @media (min-width: 1024px) {
            body.sidebar-closed #sidebar { transform: translateX(-100%) !important; }
            body.sidebar-closed #main-content { margin-left: 0 !important; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside id="sidebar" class="w-64 bg-[#1e293b] text-white flex flex-col fixed inset-y-0 left-0 z-30 transition-transform duration-300 -translate-x-full lg:translate-x-0">
            <!-- Logo / School Name -->
            <div class="px-4 py-4 border-b border-slate-600">
                <h1 class="text-lg font-bold leading-tight">{{ $__school->school_short_name ?? $__school->school_name ?? 'EduTrustSchool' }}</h1>
                <p class="text-xs text-gray-400 mt-0.5">{{ $__school->school_name ?? 'School Management System' }}</p>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1" id="sidebar-nav">
                <!-- Dashboard -->
                <a href="{{ route('admin.dashboard') }}" class="sidebar-link flex items-center px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('admin.dashboard') ? 'active bg-white/10' : 'hover:bg-white/5' }}">
                    <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    {{ __('Dashboard') }}
                </a>

                @if(auth()->user()->canAny(['academic-session.view', 'term.view', 'exam-sequence.view', 'form.view', 'stream.view', 'subject.view', 'class-section.view', 'classroom.view', 'batch.view', 'subject-enrollment.view', 'sequence-enrollment.view']))
                <!-- Academic Config -->
                <div class="sidebar-group">
                    <button onclick="toggleSubmenu('academic-menu')" class="sidebar-link w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-white/5">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                            {{ __('Academic') }}
                        </span>
                        <svg id="academic-menu-chevron" class="sidebar-chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    <div id="academic-menu" class="sidebar-submenu ml-8 space-y-1 {{ request()->routeIs('admin.sessions.*', 'admin.terms.*', 'admin.sequences.*', 'admin.forms.*', 'admin.streams.*', 'admin.subjects.*', 'admin.subject-enrollments.*', 'admin.sequence-enrollments.*', 'admin.class-sections.*', 'admin.batches.*', 'admin.rooms.*') ? 'open' : '' }}">
                        <a href="{{ route('admin.sessions.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.sessions.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Sessions') }}</a>
                        <a href="{{ route('admin.terms.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.terms.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Terms') }}</a>
                        <a href="{{ route('admin.sequences.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.sequences.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Exam Sequences') }}</a>
                        <a href="{{ route('admin.forms.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.forms.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Forms') }}</a>
                        <a href="{{ route('admin.streams.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.streams.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Streams') }}</a>
                        <a href="{{ route('admin.subjects.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.subjects.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Subjects') }}</a>
                        <a href="{{ route('admin.subject-enrollments.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.subject-enrollments.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Enroll Subjects') }}</a>
                        <a href="{{ route('admin.sequence-enrollments.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.sequence-enrollments.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Enrol Sequences') }}</a>
                        <a href="{{ route('admin.class-sections.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.class-sections.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Class Sections') }}</a>
                        <a href="{{ route('admin.rooms.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.rooms.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Classrooms') }}</a>
                        <a href="{{ route('admin.batches.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.batches.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Batches') }}</a>
                    </div>
                </div>
                @endif

                <!-- Admissions -->
                @if(auth()->user()->canAny(['admission.view', 'id-card.view']))
                <div class="sidebar-group">
                    <button onclick="toggleSubmenu('admissions-portal-menu')" class="sidebar-link w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-white/5">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            {{ __('Admissions') }}
                        </span>
                        <svg id="admissions-portal-menu-chevron" class="sidebar-chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    <div id="admissions-portal-menu" class="sidebar-submenu ml-8 space-y-1 {{ request()->routeIs('admin.admissions.*') ? 'open' : '' }}">
                        <a href="{{ route('admin.admissions.applications.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.admissions.applications.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Applications') }}</a>
                        <a href="{{ route('admin.admissions.id-cards.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.admissions.id-cards.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('ID Cards') }}</a>
                    </div>
                </div>
                @endif

                <!-- Students -->
                @if(auth()->user()->canAny(['student.view', 'group-enrol.view', 'subject-add-drop.view', 'bulk-upload.view']))
                <div class="sidebar-group">
                    <button onclick="toggleSubmenu('admissions-menu')" class="sidebar-link w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-white/5">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            {{ __('Students') }}
                        </span>
                        <svg id="admissions-menu-chevron" class="sidebar-chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    <div id="admissions-menu" class="sidebar-submenu ml-8 space-y-1 {{ request()->routeIs('admin.students.*', 'admin.bulk-upload.*', 'admin.subject-add-drop.*', 'admin.group-enrol.*') ? 'open' : '' }}">
                        <a href="{{ route('admin.students.create') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.students.create') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('New Registration') }}</a>
                        <a href="{{ route('admin.students.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.students.index') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Student List') }}</a>
                        <a href="{{ route('admin.group-enrol.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.group-enrol.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Group Enrol') }}</a>
                        <a href="{{ route('admin.subject-add-drop.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.subject-add-drop.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Subject Add/Drop') }}</a>
                        <a href="{{ route('admin.bulk-upload.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.bulk-upload.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Bulk Upload') }}</a>
                    </div>
                </div>
                @endif

                <!-- Examinations -->
                <div class="sidebar-group">
                    <button onclick="toggleSubmenu('exams-menu')" class="sidebar-link w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-white/5">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                            {{ __('Examinations') }}
                        </span>
                        <svg id="exams-menu-chevron" class="sidebar-chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    <div id="exams-menu" class="sidebar-submenu ml-8 space-y-1 {{ request()->routeIs('admin.marks.*', 'admin.report-cards.*', 'admin.exam-schedules.*', 'admin.exam-publishing.*', 'admin.documents.marksheet*') ? 'open' : '' }}">
                        <a href="{{ route('admin.exam-schedules.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.exam-schedules.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Exam Schedule') }}</a>
                        <a href="{{ route('admin.marks.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.marks.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Marks Entry') }}</a>
                        <a href="{{ route('admin.exam-publishing.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.exam-publishing.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Exam Publishing') }}</a>
                        <a href="{{ route('admin.report-cards.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.report-cards.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Report Cards') }}</a>
                        <a href="{{ route('admin.documents.marksheet') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.documents.marksheet*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Class Marksheet') }}</a>
                    </div>
                </div>

                <!-- Fees Collection -->
                <div class="sidebar-group">
                    <button onclick="toggleSubmenu('fees-menu')" class="sidebar-link w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-white/5">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ __('Fees Collection') }}
                        </span>
                        <svg id="fees-menu-chevron" class="sidebar-chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    <div id="fees-menu" class="sidebar-submenu ml-8 space-y-1 {{ request()->routeIs('admin.fee-structures.*', 'admin.payments.*', 'admin.student-fees', 'admin.fee-categories.*', 'admin.fee-discounts.*', 'admin.fee-reports.*', 'admin.collect-fees.*', 'admin.quick-assign.*', 'admin.assignment-history.*', 'admin.payment-plans.*') ? 'open' : '' }}">
                        <a href="{{ route('admin.collect-fees.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.collect-fees.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Collect Fees') }}</a>
                        <a href="{{ route('admin.payment-plans.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.payment-plans.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Payment Plans') }}</a>
                        <a href="{{ route('admin.quick-assign.create') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.quick-assign.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Quick Assign') }}</a>
                        <a href="{{ route('admin.assignment-history.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.assignment-history.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Assignment History') }}</a>
                        <a href="{{ route('admin.fee-structures.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.fee-structures.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Fee Structures') }}</a>
                        <a href="{{ route('admin.fee-categories.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.fee-categories.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Fee Categories') }}</a>
                        <a href="{{ route('admin.payments.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.payments.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Payments') }}</a>
                        <a href="{{ route('admin.fee-discounts.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.fee-discounts.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Discounts & Waivers') }}</a>
                        @can('fee-fine.view')
                        <a href="{{ route('admin.fee-fines.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.fee-fines.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Late Fees') }}</a>
                        @endcan
                        @can('student-credit.view')
                        <a href="{{ route('admin.student-credits.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.student-credits.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Student Credits') }}</a>
                        @endcan
                        <a href="{{ route('admin.fee-reports.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.fee-reports.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Fee Reports') }}</a>
                    </div>
                </div>

                <!-- Attendance -->
                <div class="sidebar-group">
                    <button onclick="toggleSubmenu('attendance-menu')" class="sidebar-link w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-white/5">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ __('Attendance') }}
                        </span>
                        <svg id="attendance-menu-chevron" class="sidebar-chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    <div id="attendance-menu" class="sidebar-submenu ml-8 space-y-1 {{ request()->routeIs('admin.attendance.*') ? 'open' : '' }}">
                        <a href="{{ route('admin.attendance.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.attendance.index', 'admin.attendance.mark') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Mark Attendance') }}</a>
                        <a href="{{ route('admin.attendance.report') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.attendance.report') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Attendance Report') }}</a>
                    </div>
                </div>

                <!-- Routines -->
                <div class="sidebar-group">
                    <button onclick="toggleSubmenu('routines-menu')" class="sidebar-link w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-white/5">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            {{ __('Routines') }}
                        </span>
                        <svg id="routines-menu-chevron" class="sidebar-chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    <div id="routines-menu" class="sidebar-submenu ml-8 space-y-1 {{ request()->routeIs('admin.timetable.*') ? 'open' : '' }}">
                        <a href="{{ route('admin.timetable.class-schedule') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.timetable.class-schedule') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Class Schedules') }}</a>
                        <a href="{{ route('admin.timetable.teacher-schedule') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.timetable.teacher-schedule') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Teacher Routines') }}</a>
                    </div>
                </div>

                <!-- Promotion -->
                @if(auth()->user()->canAny(['promotion.view']))
                <a href="{{ route('admin.promotion.index') }}" class="sidebar-link flex items-center px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('admin.promotion.*') ? 'active bg-white/10' : 'hover:bg-white/5' }}">
                    <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                    {{ __('Promotion') }}
                </a>
                @endif

                <!-- Reports -->
                <a href="{{ route('admin.reports.index') }}" class="sidebar-link flex items-center px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('admin.reports.*') ? 'active bg-white/10' : 'hover:bg-white/5' }}">
                    <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    {{ __('Reports & Analytics') }}
                </a>

                @if(auth()->user()->canAny(['staff.view', 'role-and-permission.view']))
                <!-- User Accounts -->
                <div class="sidebar-group">
                    <button onclick="toggleSubmenu('user-accounts-menu')" class="sidebar-link w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-white/5">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            {{ __('User Accounts') }}
                        </span>
                        <svg id="user-accounts-menu-chevron" class="sidebar-chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    <div id="user-accounts-menu" class="sidebar-submenu ml-8 space-y-1 {{ request()->routeIs('admin.users.*') ? 'open' : '' }}">
                        <a href="{{ route('admin.users.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.users.index') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Login Accounts') }}</a>
                        <a href="{{ route('admin.users.create') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.users.create') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Add User') }}</a>
                    </div>
                </div>

                <!-- Parent Portal & PTA -->
                <div class="sidebar-group">
                    <button onclick="toggleSubmenu('parent-pta-menu')" class="sidebar-link w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-white/5">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            {{ __('Parent Portal & PTA') }}
                        </span>
                        <svg id="parent-pta-menu-chevron" class="sidebar-chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    <div id="parent-pta-menu" class="sidebar-submenu ml-8 space-y-1 {{ request()->routeIs('admin.parent-portal.*', 'admin.parent-payments.*', 'admin.pta.*') ? 'open' : '' }}">
                        <a href="{{ route('admin.parent-portal.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.parent-portal.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Parent Accounts') }}</a>
                        @php($pendingPayments = \App\Models\ParentPaymentSubmission::where('status', 'pending')->count())
                        <a href="{{ route('admin.parent-payments.index') }}" class="flex items-center justify-between px-3 py-2 rounded text-sm {{ request()->routeIs('admin.parent-payments.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">
                            <span>{{ __('Payment Submissions') }}</span>
                            @if($pendingPayments > 0)
                                <span class="ml-2 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-amber-500 text-white text-[10px] font-bold">{{ $pendingPayments }}</span>
                            @endif
                        </a>
                        <a href="{{ route('admin.pta.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.pta.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('PTA Management') }}</a>
                    </div>
                </div>
                @endif

                <!-- Income & Expense -->
                @if(auth()->user()->canAny(['income.view', 'expense.view', 'outcome.view', 'payment-account.view', 'fund-transfer.view', 'budget.view', 'chart-of-accounts.view', 'journal-entry.view', 'general-ledger.view', 'payroll.view']))
            <div class="sidebar-group">
                <button onclick="toggleSubmenu('accounts-menu')" class="sidebar-link w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-white/5">
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                        {{ __('Income & Expense') }}
                    </span>
                    <svg id="accounts-menu-chevron" class="sidebar-chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
                <div id="accounts-menu" class="sidebar-submenu ml-8 space-y-1 {{ request()->routeIs('admin.account.*') ? 'open' : '' }}">
                    <a href="{{ route('admin.account.income.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.account.income.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Income List') }}</a>
                    <a href="{{ route('admin.account.income-category.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.account.income-category.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Income Categories') }}</a>
                    <a href="{{ route('admin.account.expense.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.account.expense.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Expense List') }}</a>
                    <a href="{{ route('admin.account.expense-category.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.account.expense-category.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Expense Categories') }}</a>
                    <a href="{{ route('admin.account.outcome.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.account.outcome.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Outcome Overview') }}</a>
                </div>
            </div>

            <!-- Payment Accounts -->
            <div class="sidebar-group">
                <button onclick="toggleSubmenu('payment-accounts-menu')" class="sidebar-link w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-white/5">
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        {{ __('Payment Accounts') }}
                    </span>
                    <svg id="payment-accounts-menu-chevron" class="sidebar-chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
                <div id="payment-accounts-menu" class="sidebar-submenu ml-8 space-y-1 {{ request()->routeIs('admin.payment-account*') ? 'open' : '' }}">
                    <a href="{{ route('admin.payment-account.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.payment-account.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Accounts') }}</a>
                    <a href="{{ route('admin.payment-account-transfer.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.payment-account-transfer.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Fund Transfers') }}</a>
                    <a href="{{ route('admin.payment-account-report.cashflow') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.payment-account-report.cashflow') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Cash Flow') }}</a>
                    <a href="{{ route('admin.payment-account-report.summary') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.payment-account-report.summary') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Summary') }}</a>
                    <a href="{{ route('admin.payment-account-report.unlinked') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.payment-account-report.unlinked') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Unlinked Transactions') }}</a>
                </div>
            </div>

                <!-- Budgets -->
                <div class="sidebar-group">
                    <button onclick="toggleSubmenu('budgets-menu')" class="sidebar-link w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-white/5">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            {{ __('Budgets') }}
                        </span>
                        <svg id="budgets-menu-chevron" class="sidebar-chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    <div id="budgets-menu" class="sidebar-submenu ml-8 space-y-1 {{ request()->routeIs('admin.budget*') ? 'open' : '' }}">
                        <a href="{{ route('admin.budget-dashboard') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.budget-dashboard') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Dashboard') }}</a>
                        <a href="{{ route('admin.budget.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.budget.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Budgets') }}</a>
                        <a href="{{ route('admin.budget-report.performance') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.budget-report.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Reports') }}</a>
                    </div>
                </div>

                <!-- Accounting (OHADA) -->
                <div class="sidebar-group">
                    <button onclick="toggleSubmenu('accounting-menu')" class="sidebar-link w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-white/5">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m-6 4h6m-6 4h4M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>
                            {{ __('Accounting (OHADA)') }}
                        </span>
                        <svg id="accounting-menu-chevron" class="sidebar-chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    <div id="accounting-menu" class="sidebar-submenu ml-8 space-y-1 {{ request()->routeIs('admin.chart-of-accounts.*', 'admin.fiscal-years.*', 'admin.journal-entries.*', 'admin.account-mappings.*', 'admin.accounting-reports.*') ? 'open' : '' }}">
                        <a href="{{ route('admin.chart-of-accounts.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.chart-of-accounts.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Chart of Accounts') }}</a>
                        <a href="{{ route('admin.fiscal-years.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.fiscal-years.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Fiscal Years') }}</a>
                        <a href="{{ route('admin.journal-entries.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.journal-entries.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Journal Entries') }}</a>
                        <a href="{{ route('admin.accounting-reports.general-ledger') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.accounting-reports.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Reports') }}</a>
                        <a href="{{ route('admin.account-mappings.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.account-mappings.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Transaction Mappings') }}</a>
                        @can('accounting-report.view')
                        <a href="{{ route('admin.accounting-reports.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.accounting-reports.index') || request()->routeIs('admin.accounting-reports.*-aging') || request()->routeIs('admin.accounting-reports.budget-vs-actual') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Accounting Reports') }}</a>
                        @endcan
                        @can('fixed-asset.view')
                        <a href="{{ route('admin.fixed-assets.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.fixed-assets.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Fixed Assets') }}</a>
                        @endcan
                    </div>
                </div>

                <!-- Human Resources -->
                <div class="sidebar-group">
                    <button onclick="toggleSubmenu('hr-menu')" class="sidebar-link w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-white/5">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            {{ __('Human Resources') }}
                        </span>
                        <svg id="hr-menu-chevron" class="sidebar-chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    <div id="hr-menu" class="sidebar-submenu ml-8 space-y-1 {{ request()->routeIs('admin.staff.*', 'admin.payroll.*', 'admin.designations.*', 'admin.work-shifts.*', 'admin.allowance-types.*', 'admin.deduction-types.*', 'admin.tax-groups.*', 'admin.tax-settings.*', 'admin.tax-report.*') ? 'open' : '' }}">
                        <a href="{{ route('admin.staff.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.staff.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Staff List') }}</a>
                        <a href="{{ route('admin.payroll.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.payroll.index', 'admin.payroll.generate', 'admin.payroll.store') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Payrolls') }}</a>
                        @can('staff-leave.view')
                        <a href="{{ route('admin.leaves.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.leaves.*') || request()->routeIs('admin.leave-types.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Staff Leave') }}</a>
                        @endcan
                        @can('staff-attendance.view')
                        <a href="{{ route('admin.staff-attendance.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.staff-attendance.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Staff Attendance') }}</a>
                        @endcan
                        <a href="{{ route('admin.payroll.report') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.payroll.report') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Payroll Reports') }}</a>
                        <a href="{{ route('admin.work-shifts.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.work-shifts.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Work Shift Types') }}</a>
                        <a href="{{ route('admin.designations.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.designations.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Designations') }}</a>
                        <a href="{{ route('admin.allowance-types.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.allowance-types.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Allowance Type') }}</a>
                        <a href="{{ route('admin.deduction-types.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.deduction-types.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Deduction Type') }}</a>
                        <a href="{{ route('admin.tax-groups.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.tax-groups.*', 'admin.tax-settings.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Tax Groups') }}</a>
                        <a href="{{ route('admin.tax-report.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.tax-report.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Tax Distribution Report') }}</a>
                    </div>
                </div>
                @endif
            </nav>

            <!-- System Settings -->
            @if(auth()->user()->canAny(['school-settings.view', 'branch.view', 'parent-portal.view', 'pta.view']))
            <div class="px-3 pb-2">
                <div class="sidebar-group">
                    <button onclick="toggleSubmenu('system-settings-menu')" class="sidebar-link w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-white/5">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            {{ __('System Settings') }}
                        </span>
                        <svg id="system-settings-menu-chevron" class="sidebar-chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    <div id="system-settings-menu" class="sidebar-submenu ml-8 space-y-1 {{ request()->routeIs('admin.roles.*') || request()->routeIs('admin.settings.*') || request()->routeIs('admin.audit-log.*') ? 'open' : '' }}">
                        @can('role-and-permission.view')
                        <a href="{{ route('admin.roles.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.roles.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Roles & Permissions') }}</a>
                        @endcan
                        @can('audit-log.view')
                        <a href="{{ route('admin.audit-log.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.audit-log.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Audit Log') }}</a>
                        @endcan
                        @if(auth()->user()->role === 'super_admin')
                        <a href="{{ route('admin.branches.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.branches.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Branches') }}</a>
                        @endif
                        @can('school-settings.view')
                        <a href="{{ route('admin.settings.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.settings.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('School Settings') }}</a>
                        <a href="{{ route('admin.configuration-health.index') }}" class="block px-3 py-2 rounded text-sm {{ request()->routeIs('admin.configuration-health.*') ? 'text-white bg-white/10' : 'text-gray-400 hover:text-white' }}">{{ __('Configuration Health') }}</a>
                        @endcan
                    </div>
                </div>
            </div>
            @endif

            <!-- User Info at bottom -->
            <div class="border-t border-slate-600 px-4 py-3">
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-slate-500 flex items-center justify-center text-sm font-bold">
                        {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}{{ strtoupper(substr(auth()->user()->last_name, 0, 1)) }}
                    </div>
                    <div class="ml-3 flex-1 min-w-0">
                        <p class="text-sm font-medium truncate">{{ auth()->user()->full_name }}</p>
                        <p class="text-xs text-gray-400 capitalize">{{ str_replace('_', ' ', auth()->user()->role) }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div id="main-content" class="flex-1 lg:ml-64 transition-all duration-300" style="min-width: 0; overflow-x: hidden;">
            <!-- Top Bar -->
            <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20">
                <div class="flex items-center justify-between px-4 lg:px-6 py-3">
                    <div class="flex items-center">
                        <!-- Sidebar toggle -->
                        <button id="sidebar-toggle" class="mr-3 p-2 rounded-lg hover:bg-gray-100">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800">@yield('title', 'Dashboard')</h2>
                            @hasSection('breadcrumb')
                                <div class="text-sm text-gray-500">@yield('breadcrumb')</div>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <span class="hidden md:block text-sm text-gray-500">{{ now()->format('l, F j, Y') }}</span>

                        {{-- Branch Switcher — only when the user can access more than one branch --}}
                        @if(\App\Support\BranchContext::showSwitcher())
                        @php($currentBranch = session(\App\Support\BranchContext::SESSION_KEY))
                        <form method="POST" action="{{ route('admin.branch-context.switch') }}" id="branch-context-form" class="flex items-center">
                            @csrf
                            <label class="sr-only" for="branch-select">{{ __('Branch') }}</label>
                            <select name="branch" id="branch-select" onchange="document.getElementById('branch-context-form').submit()"
                                    style="appearance: auto; -webkit-appearance: menulist;"
                                    class="text-sm border border-teal-200 rounded-lg px-2 py-1 text-teal-800 bg-teal-50 focus:ring-2 focus:ring-teal-500 outline-none cursor-pointer">
                                @foreach(\App\Support\BranchContext::availableBranches() as $branch)
                                    <option value="{{ $branch->id }}" {{ (int) \App\Support\BranchContext::current() === $branch->id && !\App\Support\BranchContext::isAllBranches() ? 'selected' : '' }}>{{ $branch->name }}</option>
                                @endforeach
                                <option value="all" {{ \App\Support\BranchContext::isAllBranches() ? 'selected' : '' }}>★ {{ __('All Branches') }}</option>
                            </select>
                        </form>
                        @endif

                        {{-- School Level Switcher — only when the school runs more than one level --}}
                        @if(auth()->user()->canAny(['academic-session.view', 'form.view', 'student.view']) && \App\Support\LevelContext::showSwitcher())
                        <form method="POST" action="{{ route('admin.level-context.switch') }}" id="level-context-form" class="flex items-center">
                            @csrf
                            <label class="sr-only" for="school-level-select">{{ __('School Level') }}</label>
                            <select name="school_level" id="school-level-select" onchange="document.getElementById('level-context-form').submit()"
                                    style="appearance: auto; -webkit-appearance: menulist;"
                                    class="text-sm border border-gray-200 rounded-lg px-2 py-1 text-gray-700 bg-white focus:ring-2 focus:ring-blue-500 outline-none cursor-pointer">
                                @foreach(\App\Support\LevelContext::availableLabels() as $value => $label)
                                    <option value="{{ $value }}" {{ \App\Support\LevelContext::current() === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </form>
                        @endif

                        {{-- Language Switcher --}}
                        <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden text-sm">
                            <a href="{{ route('lang.switch', 'en') }}"
                               class="px-2 py-1 {{ app()->getLocale() === 'en' ? 'bg-slate-800 text-white' : 'text-gray-600 hover:bg-gray-100' }}">EN</a>
                            <a href="{{ route('lang.switch', 'fr') }}"
                               class="px-2 py-1 {{ app()->getLocale() === 'fr' ? 'bg-slate-800 text-white' : 'text-gray-600 hover:bg-gray-100' }}">FR</a>
                        </div>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-sm text-gray-600 hover:text-red-600 transition flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                {{ __('Logout') }}
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="p-4 lg:p-6">
                <!-- Flash Messages -->
                @if(session('success'))
                    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-4 flex items-center justify-between" id="flash-success">
                        <span>{{ session('success') }}</span>
                        <button onclick="this.parentElement.remove()" class="text-green-600 hover:text-green-800">&times;</button>
                    </div>
                @endif
                @if(session('error'))
                    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-4 flex items-center justify-between">
                        <span>{{ session('error') }}</span>
                        <button onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800">&times;</button>
                    </div>
                @endif
                @if(session('warning'))
                    <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg mb-4 flex items-center justify-between">
                        <span>{{ session('warning') }}</span>
                        <button onclick="this.parentElement.remove()" class="text-amber-600 hover:text-amber-800">&times;</button>
                    </div>
                @endif

                @if(\App\Support\BranchContext::isAllBranches())
                    <div class="bg-indigo-50 border border-indigo-200 text-indigo-800 px-4 py-2 rounded-lg mb-4 text-sm flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                        {{ __('Consolidated view — showing data across all branches. Switch to a single branch to edit or record.') }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <!-- Mobile sidebar overlay -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-20 hidden lg:hidden" onclick="closeSidebar()"></div>

    {{-- Forced School Level Mode setup (appears until configured) --}}
    @include('admin.partials.level-mode-setup')

    <script>
        function toggleSubmenu(id) {
            const submenu = document.getElementById(id);
            const chevron = document.getElementById(id + '-chevron');
            submenu.classList.toggle('open');
            chevron.classList.toggle('rotated');
        }

        // Auto-open active submenus
        document.querySelectorAll('.sidebar-submenu.open').forEach(el => {
            const chevron = document.getElementById(el.id + '-chevron');
            if (chevron) chevron.classList.add('rotated');
        });

        // Mobile sidebar toggle
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');

        document.getElementById('sidebar-toggle')?.addEventListener('click', () => {
            if (window.innerWidth >= 1024) {
                document.body.classList.toggle('sidebar-closed');
            } else {
                sidebar.classList.toggle('-translate-x-full');
                overlay.classList.toggle('hidden');
            }
        });

        function closeSidebar() {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        }

        // Auto-hide flash messages
        setTimeout(() => {
            document.getElementById('flash-success')?.remove();
        }, 5000);
    </script>
    @stack('scripts')
</body>
</html>
