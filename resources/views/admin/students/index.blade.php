@extends('layouts.admin')

@section('title', __('Students'))
@section('breadcrumb', __('Student Management'))

@section('content')
<div class="space-y-4">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <h2 class="text-xl font-bold text-gray-800">{{ __('Students') }}</h2>
        <a href="{{ route('admin.students.create') }}" class="bg-[#1e293b] hover:bg-[#334155] text-white px-4 py-2 rounded-lg text-sm font-medium transition inline-flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('Admit New Student') }}
        </a>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <form method="GET" action="{{ route('admin.students.index') }}" class="flex flex-col sm:flex-row gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by name or ID...') }}"
                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
            <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                <option value="graduated" {{ request('status') == 'graduated' ? 'selected' : '' }}>{{ __('Graduated') }}</option>
                <option value="withdrawn" {{ request('status') == 'withdrawn' ? 'selected' : '' }}>{{ __('Withdrawn') }}</option>
                <option value="suspended" {{ request('status') == 'suspended' ? 'selected' : '' }}>{{ __('Suspended') }}</option>
            </select>
            <select name="class_section_id" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                <option value="">{{ __('All Classes') }}</option>
                @foreach($classSections as $cs)
                    <option value="{{ $cs->id }}" {{ request('class_section_id') == $cs->id ? 'selected' : '' }}>{{ $cs->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">{{ __('Search') }}</button>
            @if(request()->anyFilled(['search','status','class_section_id']))
                <a href="{{ route('admin.students.index') }}" class="text-gray-500 hover:text-gray-700 px-3 py-2 text-sm">{{ __('Clear') }}</a>
            @endif
        </form>
    </div>

    {{-- Students Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                    <tr>
                        <th class="px-4 py-3 text-left">{{ __('Student ID') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Name') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Gender') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Class') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Guardian Phone') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($students as $student)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 font-mono text-xs text-blue-600">{{ $student->student_id }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $student->full_name }}</td>
                        <td class="px-4 py-3 text-gray-600 capitalize">{{ $student->gender }}</td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $student->currentEnrollment?->classSection?->name ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $student->guardian?->emergency_contact_phone ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $colors = ['active' => 'green', 'graduated' => 'blue', 'withdrawn' => 'yellow', 'suspended' => 'red', 'expelled' => 'red'];
                                $color = $colors[$student->status] ?? 'gray';
                            @endphp
                            <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $color }}-100 text-{{ $color }}-700 capitalize">
                                {{ $student->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('admin.students.show', $student) }}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">{{ __('View') }}</a>
                                <a href="{{ route('admin.students.edit', $student) }}" class="text-amber-600 hover:text-amber-800 text-xs font-medium">{{ __('Edit') }}</a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                            <p class="font-medium">{{ __('No students found') }}</p>
                            <p class="text-sm mt-1">{{ __('Begin by admitting a new student.') }}</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($students->hasPages())
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $students->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
