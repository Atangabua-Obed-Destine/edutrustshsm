@extends('layouts.admin')

@section('title', __('Mark Attendance'))

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Mark Attendance') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('Record daily student attendance by class') }}</p>
        </div>
        <a href="{{ route('admin.attendance.report') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            {{ __('View Reports') }}
        </a>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
        <div class="flex items-center">
            <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-green-800 text-sm font-medium">{{ session('success') }}</span>
        </div>
    </div>
    @endif

    {{-- Selection Form --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form method="GET" action="{{ route('admin.attendance.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Class') }}</label>
                <select name="class_section_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-slate-500 focus:border-slate-500" required>
                    <option value="">— {{ __('Select Class') }} —</option>
                    @foreach($classSections as $cs)
                        <option value="{{ $cs->id }}" {{ $classSectionId == $cs->id ? 'selected' : '' }}>{{ $cs->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Date') }}</label>
                <input type="date" name="date" value="{{ $date }}" max="{{ now()->format('Y-m-d') }}"
                       class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-slate-500 focus:border-slate-500" required>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full px-6 py-2.5 bg-slate-800 text-white rounded-lg hover:bg-slate-700 font-medium text-sm transition">
                    {{ __('Load Students') }}
                </button>
            </div>
        </form>
    </div>

    {{-- Attendance Grid --}}
    @if($classSectionId && $enrollments->count() > 0)
    <form method="POST" action="{{ route('admin.attendance.store') }}" id="attendanceForm">
        @csrf
        <input type="hidden" name="class_section_id" value="{{ $classSectionId }}">
        <input type="hidden" name="date" value="{{ $date }}">

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ $selectedClass->name ?? 'Class' }}</h2>
                    <p class="text-sm text-gray-500">{{ \Carbon\Carbon::parse($date)->format('l, F j, Y') }} &middot; {{ $enrollments->count() }} {{ __('students') }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="markAll('present')" class="px-3 py-1.5 text-xs font-medium rounded-md bg-green-100 text-green-700 hover:bg-green-200 transition">{{ __('All Present') }}</button>
                    <button type="button" onclick="markAll('absent')" class="px-3 py-1.5 text-xs font-medium rounded-md bg-red-100 text-red-700 hover:bg-red-200 transition">{{ __('All Absent') }}</button>
                </div>
            </div>

            {{-- Quick Stats Bar --}}
            <div class="px-6 py-3 border-b border-gray-100 bg-white" id="statsBar">
                <div class="flex items-center gap-6 text-sm">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
                        {{ __('Present:') }} <strong id="countPresent">0</strong>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
                        {{ __('Absent:') }} <strong id="countAbsent">0</strong>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-yellow-500"></span>
                        {{ __('Late:') }} <strong id="countLate">0</strong>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                        {{ __('Excused:') }} <strong id="countExcused">0</strong>
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-10">#</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">{{ __('Student') }}</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">{{ __('Status') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">{{ __('Reason / Note') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @foreach($enrollments->values() as $i => $enrollment)
                        @php $existing = $existingAttendance->get($enrollment->id); @endphp
                        <tr class="hover:bg-gray-50 transition attendance-row" data-enrollment="{{ $enrollment->id }}">
                            <td class="px-6 py-3 text-sm text-gray-500">{{ $i + 1 }}</td>
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-slate-800 text-white flex items-center justify-center text-xs font-bold shrink-0">
                                        {{ strtoupper(substr($enrollment->student->first_name, 0, 1) . substr($enrollment->student->last_name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ $enrollment->student->last_name }} {{ $enrollment->student->first_name }}</div>
                                        <div class="text-xs text-gray-400">{{ $enrollment->student->student_id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    @foreach(['present' => 'P', 'absent' => 'A', 'late' => 'L', 'excused' => 'E'] as $status => $label)
                                    <label class="status-label cursor-pointer">
                                        <input type="radio"
                                               name="attendance[{{ $enrollment->id }}][status]"
                                               value="{{ $status }}"
                                               class="hidden status-radio"
                                               data-status="{{ $status }}"
                                               {{ ($existing?->status ?? 'present') === $status ? 'checked' : '' }}
                                               onchange="updateStats()">
                                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-xs font-bold border-2 transition
                                            @if($status === 'present') border-green-300 text-green-700 peer-checked:bg-green-500
                                            @elseif($status === 'absent') border-red-300 text-red-700
                                            @elseif($status === 'late') border-yellow-300 text-yellow-700
                                            @else border-blue-300 text-blue-700
                                            @endif
                                            status-btn status-{{ $status }}"
                                            data-status="{{ $status }}">
                                            {{ $label }}
                                        </span>
                                    </label>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-6 py-3">
                                <input type="text" name="attendance[{{ $enrollment->id }}][reason]"
                                       value="{{ $existing?->reason }}"
                                       placeholder="{{ __('Optional note...') }}"
                                       class="w-full text-sm rounded-md border-gray-200 focus:ring-1 focus:ring-slate-400 focus:border-slate-400 placeholder-gray-300">
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end">
                <button type="submit" class="inline-flex items-center px-6 py-2.5 bg-slate-800 text-white rounded-lg hover:bg-slate-700 font-medium text-sm transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ __('Save Attendance') }}
                </button>
            </div>
        </div>
    </form>
    @elseif($classSectionId && $enrollments->count() === 0)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
        <h3 class="text-lg font-medium text-gray-900 mb-1">{{ __('No Active Students') }}</h3>
        <p class="text-gray-500">{{ __('This class has no active student enrollments.') }}</p>
    </div>
    @else
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <h3 class="text-lg font-medium text-gray-900 mb-1">{{ __('Select a Class') }}</h3>
        <p class="text-gray-500">{{ __('Choose a class and date above to begin marking attendance.') }}</p>
    </div>
    @endif
</div>

@push('scripts')
<script>
    function updateStats() {
        const counts = { present: 0, absent: 0, late: 0, excused: 0 };
        document.querySelectorAll('.status-radio:checked').forEach(radio => {
            counts[radio.dataset.status]++;
        });
        document.getElementById('countPresent').textContent = counts.present;
        document.getElementById('countAbsent').textContent = counts.absent;
        document.getElementById('countLate').textContent = counts.late;
        document.getElementById('countExcused').textContent = counts.excused;

        // Update button visual states
        document.querySelectorAll('.status-label').forEach(label => {
            const radio = label.querySelector('.status-radio');
            const btn = label.querySelector('.status-btn');
            const s = btn.dataset.status;
            btn.classList.remove('bg-green-500', 'bg-red-500', 'bg-yellow-500', 'bg-blue-500', 'text-white');
            if (radio.checked) {
                const colors = { present: 'bg-green-500', absent: 'bg-red-500', late: 'bg-yellow-500', excused: 'bg-blue-500' };
                btn.classList.add(colors[s], 'text-white');
            }
        });
    }

    function markAll(status) {
        document.querySelectorAll(`.status-radio[data-status="${status}"]`).forEach(radio => {
            radio.checked = true;
        });
        updateStats();
    }

    // Init on load
    document.addEventListener('DOMContentLoaded', updateStats);
</script>
@endpush
@endsection
