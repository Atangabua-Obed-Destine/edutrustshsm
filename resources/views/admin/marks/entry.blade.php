@extends('layouts.admin')

@section('title', __('Enter Marks'))
@section('breadcrumb', __('Examinations') . ' > ' . __('Marks Entry') . ' > ' . $classSection->name . ' — ' . $subject->name)

@section('content')
<div class="space-y-6">

    {{-- Header Info --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">{{ $classSection->name }} — {{ $subject->name }}</h3>
                <p class="text-sm text-gray-500 mt-1">
                    {{ $sequence->term->name ?? '' }} &raquo; {{ $sequence->name }} &bull;
                    {{ $enrollments->count() }} {{ __('students enrolled') }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                @php
                    $statusColors = ['draft' => 'bg-gray-100 text-gray-700', 'submitted' => 'bg-blue-100 text-blue-700', 'approved' => 'bg-green-100 text-green-700', 'returned' => 'bg-red-100 text-red-700'];
                @endphp
                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full {{ $statusColors[$submission->status] ?? 'bg-gray-100 text-gray-700' }}">
                    {{ ucfirst($submission->status) }}
                </span>
                <a href="{{ route('admin.marks.index') }}" class="text-sm text-gray-500 hover:text-gray-700 underline">
                    &larr; {{ __('Back') }}
                </a>
            </div>
        </div>

        @if($submission->status === 'returned' && $submission->admin_comment)
            <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                <strong>{{ __('Returned:') }}</strong> {{ $submission->admin_comment }}
            </div>
        @endif
    </div>

    {{-- Marks Entry Grid --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <form method="POST" action="{{ route('admin.marks.save') }}" id="marksForm">
            @csrf
            <input type="hidden" name="class_section_id" value="{{ $classSection->id }}">
            <input type="hidden" name="subject_id" value="{{ $subject->id }}">
            <input type="hidden" name="sequence_id" value="{{ $sequence->id }}">

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                        <tr>
                            <th class="px-4 py-3 text-left w-10">#</th>
                            <th class="px-4 py-3 text-left">{{ __('Student Name') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Student ID') }}</th>
                            <th class="px-4 py-3 text-center w-32">{{ __('Score (0–20)') }}</th>
                            <th class="px-4 py-3 text-center w-20">{{ __('Absent') }}</th>
                            <th class="px-4 py-3 text-center w-20">{{ __('Grade') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($enrollments as $i => $enrollment)
                        @php
                            $mark = $existingMarks[$enrollment->id] ?? null;
                            $isLocked = in_array($submission->status, ['submitted', 'approved']);
                        @endphp
                        <tr class="hover:bg-gray-50" id="row-{{ $enrollment->id }}">
                            <td class="px-4 py-2.5 text-gray-400">{{ $i + 1 }}</td>
                            <td class="px-4 py-2.5 font-medium text-gray-800">
                                {{ $enrollment->student->last_name }}, {{ $enrollment->student->first_name }}
                            </td>
                            <td class="px-4 py-2.5 text-gray-500">{{ $enrollment->student->student_id }}</td>
                            <td class="px-4 py-2.5 text-center">
                                <input type="hidden" name="marks[{{ $i }}][enrollment_id]" value="{{ $enrollment->id }}">
                                <input type="number"
                                       name="marks[{{ $i }}][score]"
                                       id="score-{{ $enrollment->id }}"
                                       value="{{ $mark && !$mark->is_absent ? $mark->score : '' }}"
                                       min="0" max="20" step="0.1"
                                       class="w-24 px-2 py-1.5 border border-gray-300 rounded text-center text-sm focus:ring-2 focus:ring-blue-500 outline-none score-input"
                                       data-row="{{ $enrollment->id }}"
                                       {{ $isLocked ? 'disabled' : '' }}
                                       {{ $mark && $mark->is_absent ? 'disabled' : '' }}>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <input type="checkbox"
                                       name="marks[{{ $i }}][is_absent]"
                                       value="1"
                                       id="absent-{{ $enrollment->id }}"
                                       class="w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-red-500 absent-checkbox"
                                       data-row="{{ $enrollment->id }}"
                                       {{ $mark && $mark->is_absent ? 'checked' : '' }}
                                       {{ $isLocked ? 'disabled' : '' }}>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <span id="grade-{{ $enrollment->id }}" class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full
                                    {{ $mark && $mark->grade ? 'bg-blue-100 text-blue-700' : 'text-gray-400' }}">
                                    {{ $mark->grade ?? '—' }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Stats Bar --}}
            @if($submission->marks_entered > 0)
            <div class="px-6 py-3 bg-gray-50 border-t border-gray-100 flex flex-wrap gap-6 text-sm">
                <div><span class="text-gray-500">{{ __('Entered:') }}</span> <strong>{{ $submission->marks_entered }}</strong> / {{ $submission->total_students }}</div>
                <div><span class="text-gray-500">{{ __('Class Average:') }}</span> <strong>{{ $submission->class_average !== null ? number_format($submission->class_average, 2) : '—' }}</strong></div>
                <div><span class="text-gray-500">{{ __('Highest:') }}</span> <strong>{{ $submission->highest_mark !== null ? number_format($submission->highest_mark, 1) : '—' }}</strong></div>
                <div><span class="text-gray-500">{{ __('Lowest:') }}</span> <strong>{{ $submission->lowest_mark !== null ? number_format($submission->lowest_mark, 1) : '—' }}</strong></div>
                <div><span class="text-gray-500">{{ __('Pass Rate:') }}</span> <strong>{{ $submission->pass_rate !== null ? number_format($submission->pass_rate, 1) . '%' : '—' }}</strong></div>
            </div>
            @endif

            {{-- Action Buttons --}}
            <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center gap-3">
                @if(in_array($submission->status, ['draft', 'returned']))
                    <button type="submit" class="bg-[#1e293b] hover:bg-[#334155] text-white px-5 py-2 rounded-lg text-sm font-medium transition">
                        {{ __('Save Marks') }}
                    </button>
                @endif

                @if($submission->status === 'draft' && $submission->marks_entered > 0)
                    <form method="POST" action="{{ route('admin.marks.submit', $submission) }}" class="inline"
                          onsubmit="return confirm('Submit these marks for approval? You will not be able to edit them until approved or returned.')">
                        @csrf
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition">
                            {{ __('Submit for Approval') }}
                        </button>
                    </form>
                @endif

                @if($submission->status === 'submitted' && in_array(auth()->user()->role, ['super_admin', 'admin']))
                    <form method="POST" action="{{ route('admin.marks.approve', $submission) }}" class="inline"
                          onsubmit="return confirm('Approve these marks? This action is final.')">
                        @csrf
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition">
                            {{ __('Approve') }}
                        </button>
                    </form>

                    {{-- Return Modal Trigger --}}
                    <button type="button" onclick="document.getElementById('returnModal').classList.remove('hidden')"
                            class="bg-red-600 hover:bg-red-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition">
                        {{ __('Return for Correction') }}
                    </button>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- Return Modal --}}
@if($submission->status === 'submitted')
<div id="returnModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
        <h4 class="text-lg font-semibold text-gray-800 mb-3">{{ __('Return Marks for Correction') }}</h4>
        <form method="POST" action="{{ route('admin.marks.return', $submission) }}">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Reason / Comment') }}</label>
                <textarea name="admin_comment" rows="3" required
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 outline-none"
                          placeholder="{{ __('Explain why marks are being returned...') }}"></textarea>
            </div>
            <div class="mt-4 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('returnModal').classList.add('hidden')"
                        class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('Cancel') }}</button>
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition">
                    {{ __('Return Marks') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Grade lookup & absent toggle JS --}}
<script>
    const gradeScales = @json($gradeScales);

    function getGrade(score) {
        for (const gs of gradeScales) {
            if (score >= parseFloat(gs.min_mark) && score <= parseFloat(gs.max_mark)) {
                return gs.grade;
            }
        }
        return '—';
    }

    document.querySelectorAll('.score-input').forEach(input => {
        input.addEventListener('input', function () {
            const rowId = this.dataset.row;
            const gradeEl = document.getElementById('grade-' + rowId);
            const val = parseFloat(this.value);
            if (!isNaN(val) && val >= 0 && val <= 20) {
                const g = getGrade(val);
                gradeEl.textContent = g;
                gradeEl.className = 'inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-700';
            } else {
                gradeEl.textContent = '—';
                gradeEl.className = 'inline-flex px-2 py-0.5 text-xs font-semibold rounded-full text-gray-400';
            }
        });
    });

    document.querySelectorAll('.absent-checkbox').forEach(cb => {
        cb.addEventListener('change', function () {
            const rowId = this.dataset.row;
            const scoreInput = document.getElementById('score-' + rowId);
            const gradeEl = document.getElementById('grade-' + rowId);
            if (this.checked) {
                scoreInput.value = '';
                scoreInput.disabled = true;
                gradeEl.textContent = 'ABS';
                gradeEl.className = 'inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-700';
            } else {
                scoreInput.disabled = false;
                gradeEl.textContent = '—';
                gradeEl.className = 'inline-flex px-2 py-0.5 text-xs font-semibold rounded-full text-gray-400';
            }
        });
    });
</script>
@endsection
