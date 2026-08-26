@extends('layouts.admin')

@section('title', __('Dashboard'))

@section('content')
<div class="space-y-6">
    <!-- Welcome Card -->
    <div class="bg-gradient-to-r from-[#1e293b] to-[#334155] rounded-xl p-6 text-white">
        <h2 class="text-2xl font-bold">{{ __('Welcome,') }} {{ auth()->user()->first_name }}!</h2>
        <p class="text-gray-300 mt-1">{{ $school?->school_name ?? 'EduTrustSchool Management System' }}</p>
        @if($currentSession)
            <p class="text-sm text-gray-400 mt-2">{{ __('Current Session:') }} <span class="text-white font-medium">{{ $currentSession->name }}</span></p>
        @else
            <p class="text-sm text-yellow-300 mt-2">{{ __('No active academic session.') }} <a href="{{ route('admin.sessions.create') }}" class="underline">{{ __('Create one') }}</a></p>
        @endif
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Active Students -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">{{ __('Active Students') }}</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">{{ number_format($stats['active_students']) }}</p>
                </div>
                <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
            </div>
        </div>

        <!-- Teachers -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">{{ __('Teachers') }}</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">{{ number_format($stats['total_teachers']) }}</p>
                </div>
                <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
            </div>
        </div>

        <!-- Classes -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">{{ __('Active Classes') }}</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">{{ number_format($stats['total_classes']) }}</p>
                </div>
                <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
            </div>
        </div>

        <!-- Present Today -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">{{ __('Present Today') }}</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">{{ number_format($stats['present_today']) }}</p>
                    @if($stats['absent_today'] > 0)
                        <p class="text-xs text-red-500 mt-1">{{ $stats['absent_today'] }} {{ __('absent') }}</p>
                    @endif
                </div>
                <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    @if(in_array(auth()->user()->role, ['super_admin', 'admin']))
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">{{ __('Quick Actions') }}</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <a href="{{ route('admin.sessions.index') }}" class="flex flex-col items-center p-4 rounded-lg bg-gray-50 hover:bg-blue-50 transition">
                <svg class="w-8 h-8 text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span class="text-xs font-medium text-gray-700 text-center">{{ __('Manage Sessions') }}</span>
            </a>
            <a href="{{ route('admin.students.create') }}" class="flex flex-col items-center p-4 rounded-lg bg-gray-50 hover:bg-blue-50 transition">
                <svg class="w-8 h-8 text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                <span class="text-xs font-medium text-gray-700 text-center">{{ __('Add Student') }}</span>
            </a>
            <a href="{{ route('admin.subjects.index') }}" class="flex flex-col items-center p-4 rounded-lg bg-gray-50 hover:bg-blue-50 transition">
                <svg class="w-8 h-8 text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                <span class="text-xs font-medium text-gray-700 text-center">{{ __('Manage Subjects') }}</span>
            </a>
            <a href="{{ route('admin.class-sections.index') }}" class="flex flex-col items-center p-4 rounded-lg bg-gray-50 hover:bg-blue-50 transition">
                <svg class="w-8 h-8 text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span class="text-xs font-medium text-gray-700 text-center">{{ __('Class Sections') }}</span>
            </a>
        </div>
    </div>
    @endif
</div>
@endsection
