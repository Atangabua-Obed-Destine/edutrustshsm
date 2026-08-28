@extends('layouts.admin')
@section('title', __('Student Promotion'))

@section('content')
<div class="max-w-7xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ __('Student Promotion & Progression') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('Promote, repeat, or graduate students based on academic performance') }}</p>
    </div>

    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-emerald-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-sm text-emerald-700 font-medium">{{ session('success') }}</p>
        </div>
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-sm text-red-700 font-medium">{{ session('error') }}</p>
        </div>
    </div>
    @endif

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Academic Session') }}</label>
                <select name="from_session_id" class="w-full rounded-lg border-gray-300 shadow-sm text-sm">
                    @foreach($sessions as $s)
                        <option value="{{ $s->id }}" {{ $fromSessionId == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Class') }}</label>
                <select name="class_section_id" class="w-full rounded-lg border-gray-300 shadow-sm text-sm">
                    <option value="">{{ __('Select a class...') }}</option>
                    @foreach($classSections as $cs)
                        <option value="{{ $cs->id }}" {{ $classSectionId == $cs->id ? 'selected' : '' }}>{{ $cs->name }} ({{ $cs->form->name }})</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-5 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 text-sm font-medium transition">
                {{ __('Load Students') }}
            </button>
        </form>
    </div>

    {{-- Promotion Info --}}
    @if($selectedClass)
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">{{ __('Class') }}</p>
            <p class="text-lg font-bold text-gray-800 mt-1">{{ $selectedClass->name }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">{{ __('Total Students') }}</p>
            <p class="text-lg font-bold text-gray-800 mt-1">{{ $students->count() }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">{{ __('Pass Threshold') }}</p>
            <p class="text-lg font-bold text-indigo-600 mt-1">{{ $threshold }}/20</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">{{ __('Next Session') }}</p>
            <p class="text-lg font-bold {{ $nextSession ? 'text-emerald-600' : 'text-red-600' }} mt-1">{{ $nextSession ? $nextSession->name : __('Not Created') }}</p>
        </div>
    </div>

    @if($students->isNotEmpty())
    <form action="{{ route('admin.promotion.process') }}" method="POST">
        @csrf
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">{{ __('Student List') }} — {{ $selectedClass->name }}</h3>
                <div class="flex items-center gap-3 text-xs">
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> {{ __('Promote') }}</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-500"></span> {{ __('Repeat') }}</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-500"></span> {{ __('Graduate') }}</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-gray-400"></span> {{ __('Skip') }}</span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3 text-left">#</th>
                            <th class="px-4 py-3 text-left">{{ __('Student') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Average') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Recommendation') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Decision') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Target Class') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($students as $i => $enrollment)
                        <tr class="hover:bg-gray-50" id="row-{{ $enrollment->id }}">
                            <td class="px-4 py-3 text-gray-400">{{ $i + 1 }}</td>
                            <td class="px-4 py-3">
                                <input type="hidden" name="decisions[{{ $i }}][enrollment_id]" value="{{ $enrollment->id }}">
                                <div class="font-medium text-gray-900">{{ $enrollment->student->last_name }} {{ $enrollment->student->first_name }}</div>
                                <div class="text-xs text-gray-400">{{ $enrollment->student->student_id }}</div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($enrollment->computed_average !== null)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-bold {{ $enrollment->computed_average >= $threshold ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                        {{ $enrollment->computed_average }}
                                    </span>
                                @else
                                    <span class="text-gray-400 text-xs">N/A</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($enrollment->recommended_decision === 'promote')
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                                        {{ __('Promote') }}
                                    </span>
                                @elseif($enrollment->recommended_decision === 'repeat')
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-red-600">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        {{ __('Repeat') }}
                                    </span>
                                @else
                                    <span class="text-gray-400 text-xs">{{ __('No Data') }}</span>
                                @endif
                                @if($enrollment->ineligible_reason)
                                    {{-- Say why now, rather than refusing the promotion after it is submitted. --}}
                                    <div class="text-[11px] text-gray-500 mt-1 leading-tight">{{ $enrollment->ineligible_reason }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    {{-- Promotion is gated on a complete, passing year, so the option is
                                         off where the server would refuse it anyway. --}}
                                    <label class="flex items-center gap-1 {{ $enrollment->is_eligible ? 'cursor-pointer' : 'cursor-not-allowed opacity-40' }}" @if(! $enrollment->is_eligible) title="{{ $enrollment->ineligible_reason }}" @endif>
                                        <input type="radio" name="decisions[{{ $i }}][action]" value="promote" {{ $enrollment->recommended_decision === 'promote' ? 'checked' : '' }} @disabled(! $enrollment->is_eligible) class="text-emerald-600 focus:ring-emerald-500 disabled:opacity-50" onchange="toggleTargetClass({{ $enrollment->id }}, true)">
                                        <span class="text-xs text-gray-600">P</span>
                                    </label>
                                    <label class="flex items-center gap-1 cursor-pointer">
                                        <input type="radio" name="decisions[{{ $i }}][action]" value="repeat" {{ $enrollment->recommended_decision === 'repeat' ? 'checked' : '' }} class="text-amber-600 focus:ring-amber-500" onchange="toggleTargetClass({{ $enrollment->id }}, true)">
                                        <span class="text-xs text-gray-600">R</span>
                                    </label>
                                    <label class="flex items-center gap-1 cursor-pointer">
                                        <input type="radio" name="decisions[{{ $i }}][action]" value="graduate" class="text-blue-600 focus:ring-blue-500" onchange="toggleTargetClass({{ $enrollment->id }}, false)">
                                        <span class="text-xs text-gray-600">G</span>
                                    </label>
                                    <label class="flex items-center gap-1 cursor-pointer">
                                        <input type="radio" name="decisions[{{ $i }}][action]" value="skip" class="text-gray-400 focus:ring-gray-400" onchange="toggleTargetClass({{ $enrollment->id }}, false)">
                                        <span class="text-xs text-gray-600">S</span>
                                    </label>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <select name="decisions[{{ $i }}][target_class_id]" id="target-{{ $enrollment->id }}" class="w-full rounded-lg border-gray-300 text-xs shadow-sm">
                                    <option value="">{{ __('-- Select --') }}</option>
                                    @foreach($targetClasses as $tc)
                                        <option value="{{ $tc->id }}">{{ $tc->name }} ({{ $tc->form->name }})</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between bg-gray-50">
                <p class="text-xs text-gray-500">
                    <strong>P</strong> = Promote &middot; <strong>R</strong> = Repeat &middot; <strong>G</strong> = Graduate &middot; <strong>S</strong> = Skip
                </p>
                @if($nextSession)
                <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium transition"
                        onclick="return confirm('Are you sure? This will process all promotion decisions. This action cannot be easily undone.')">
                    {{ __('Process Promotions') }}
                </button>
                @else
                <p class="text-sm text-red-600 font-medium">{{ __('Create a next academic session to enable promotions.') }}</p>
                @endif
            </div>
        </div>
    </form>
    @else
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        <p class="text-gray-500">{{ __('No active students found in this class.') }}</p>
    </div>
    @endif
    @else
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
        <svg class="w-16 h-16 text-gray-200 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
        <h3 class="text-base font-semibold text-gray-600">{{ __('Select a class to begin') }}</h3>
        <p class="text-sm text-gray-400 mt-1">{{ __('Choose an academic session and class section above, then click "Load Students".') }}</p>
    </div>
    @endif
</div>

<script>
function toggleTargetClass(enrollmentId, show) {
    const select = document.getElementById('target-' + enrollmentId);
    if (select) {
        select.style.opacity = show ? '1' : '0.4';
        if (!show) select.value = '';
    }
}
</script>
@endsection
