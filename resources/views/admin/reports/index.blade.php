@extends('layouts.admin')
@section('title', __('Reports & Analytics'))

@section('content')
<div class="max-w-7xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ __('Reports & Analytics') }}</h1>
        <p class="text-sm text-gray-500 mt-1">
            {{ __('School-wide insights for') }} {{ $currentSession ? $currentSession->name : __('No Active Session') }}
        </p>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl shadow p-5 text-white">
            <div class="flex items-center justify-between">
                <p class="text-xs uppercase tracking-wider opacity-80">{{ __('Students') }}</p>
                <svg class="w-8 h-8 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/></svg>
            </div>
            <p class="text-3xl font-bold mt-2">{{ number_format($totalStudents) }}</p>
            <p class="text-xs opacity-60 mt-1">{{ __('Active enrollments') }}</p>
        </div>
        <div class="bg-gradient-to-br from-indigo-600 to-indigo-700 rounded-xl shadow p-5 text-white">
            <div class="flex items-center justify-between">
                <p class="text-xs uppercase tracking-wider opacity-80">{{ __('Teachers') }}</p>
                <svg class="w-8 h-8 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
            </div>
            <p class="text-3xl font-bold mt-2">{{ number_format($totalTeachers) }}</p>
            <p class="text-xs opacity-60 mt-1">{{ __('Active staff') }}</p>
        </div>
        <div class="bg-gradient-to-br from-emerald-600 to-emerald-700 rounded-xl shadow p-5 text-white">
            <div class="flex items-center justify-between">
                <p class="text-xs uppercase tracking-wider opacity-80">{{ __('Classes') }}</p>
                <svg class="w-8 h-8 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            <p class="text-3xl font-bold mt-2">{{ number_format($totalClasses) }}</p>
            <p class="text-xs opacity-60 mt-1">{{ __('Active sections') }}</p>
        </div>
        <div class="bg-gradient-to-br from-amber-500 to-amber-600 rounded-xl shadow p-5 text-white">
            <div class="flex items-center justify-between">
                <p class="text-xs uppercase tracking-wider opacity-80">{{ __('Attendance (30d)') }}</p>
                <svg class="w-8 h-8 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            </div>
            <p class="text-3xl font-bold mt-2">{{ $attendanceRate }}%</p>
            <div class="w-full bg-white/20 rounded-full h-1.5 mt-2">
                <div class="bg-white h-1.5 rounded-full" style="width: {{ min($attendanceRate, 100) }}%"></div>
            </div>
        </div>
    </div>

    {{-- Fee Overview --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wider">{{ __('Fee Expected') }}</p>
            <p class="text-2xl font-bold text-gray-800 mt-2">{{ number_format($feeExpected) }} <span class="text-sm font-normal text-gray-400">XAF</span></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-emerald-200 p-5">
            <p class="text-xs text-emerald-600 uppercase tracking-wider">{{ __('Fee Collected') }}</p>
            <p class="text-2xl font-bold text-emerald-700 mt-2">{{ number_format($feeCollected) }} <span class="text-sm font-normal text-emerald-400">XAF</span></p>
            @if($feeExpected > 0)
            <div class="w-full bg-emerald-100 rounded-full h-2 mt-2">
                <div class="bg-emerald-500 h-2 rounded-full" style="width: {{ min(round(($feeCollected / $feeExpected) * 100), 100) }}%"></div>
            </div>
            <p class="text-xs text-emerald-500 mt-1">{{ round(($feeCollected / $feeExpected) * 100, 1) }}% {{ __('collection rate') }}</p>
            @endif
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-red-200 p-5">
            <p class="text-xs text-red-600 uppercase tracking-wider">{{ __('Outstanding') }}</p>
            <p class="text-2xl font-bold text-red-700 mt-2">{{ number_format($feeExpected - $feeCollected) }} <span class="text-sm font-normal text-red-400">XAF</span></p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Gender Distribution --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-700">{{ __('Gender Distribution') }}</h3>
            </div>
            <div class="p-6">
                @php
                    $maleCount = $genderStats['male'] ?? 0;
                    $femaleCount = $genderStats['female'] ?? 0;
                    $genderTotal = $maleCount + $femaleCount;
                @endphp
                @if($genderTotal > 0)
                <div class="flex items-center gap-6">
                    <div class="relative w-32 h-32 flex-shrink-0">
                        @php $malePct = round(($maleCount / $genderTotal) * 100); @endphp
                        <svg viewBox="0 0 36 36" class="w-32 h-32">
                            <circle cx="18" cy="18" r="15.5" fill="none" stroke="#e5e7eb" stroke-width="3"/>
                            <circle cx="18" cy="18" r="15.5" fill="none" stroke="#3b82f6" stroke-width="3"
                                    stroke-dasharray="{{ $malePct }} {{ 100 - $malePct }}" stroke-dashoffset="25" stroke-linecap="round"/>
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-sm font-bold text-gray-700">{{ $genderTotal }}</span>
                        </div>
                    </div>
                    <div class="flex-1 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                                <span class="text-sm text-gray-700">{{ __('Male') }}</span>
                            </div>
                            <span class="text-sm font-bold text-gray-800">{{ $maleCount }} <span class="text-xs text-gray-400 font-normal">({{ $malePct }}%)</span></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-pink-500"></span>
                                <span class="text-sm text-gray-700">{{ __('Female') }}</span>
                            </div>
                            <span class="text-sm font-bold text-gray-800">{{ $femaleCount }} <span class="text-xs text-gray-400 font-normal">({{ 100 - $malePct }}%)</span></span>
                        </div>
                    </div>
                </div>
                @else
                <p class="text-center text-gray-400 text-sm py-4">{{ __('No enrollment data.') }}</p>
                @endif
            </div>
        </div>

        {{-- Enrollment by Form --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-700">{{ __('Enrollment by Form') }}</h3>
            </div>
            <div class="p-6">
                @if($enrollmentByForm->isNotEmpty())
                @php $maxEnroll = $enrollmentByForm->max() ?: 1; @endphp
                <div class="space-y-3">
                    @foreach($enrollmentByForm as $formName => $count)
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="font-medium text-gray-700">{{ $formName }}</span>
                            <span class="text-gray-500">{{ $count }} {{ __('students') }}</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-3">
                            <div class="bg-indigo-500 h-3 rounded-full transition-all" style="width: {{ ($count / $maxEnroll) * 100 }}%"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-center text-gray-400 text-sm py-4">{{ __('No forms data.') }}</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Class Performance --}}
    @if(!empty($classPerformance) && (is_countable($classPerformance) ? count($classPerformance) > 0 : $classPerformance->isNotEmpty()))
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-700">{{ __('Class Performance — Current Term') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3 text-left">{{ __('Class') }}</th>
                        <th class="px-6 py-3 text-center">{{ __('Students') }}</th>
                        <th class="px-6 py-3 text-center">{{ __('Avg Score') }}</th>
                        <th class="px-6 py-3 text-center">{{ __('Pass Count') }}</th>
                        <th class="px-6 py-3 text-center">{{ __('Pass Rate') }}</th>
                        <th class="px-6 py-3 text-center">{{ __('Performance') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($classPerformance as $cp)
                    @php $passRate = $cp->student_count > 0 ? round(($cp->pass_count / $cp->student_count) * 100) : 0; @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 font-medium text-gray-900">{{ $cp->class_section_name ?? __('N/A') }}</td>
                        <td class="px-6 py-3 text-center text-gray-600">{{ $cp->student_count }}</td>
                        <td class="px-6 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold {{ $cp->avg_score >= 10 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                {{ number_format($cp->avg_score, 2) }}/20
                            </span>
                        </td>
                        <td class="px-6 py-3 text-center text-emerald-600 font-semibold">{{ $cp->pass_count }}</td>
                        <td class="px-6 py-3 text-center text-gray-600">{{ $passRate }}%</td>
                        <td class="px-6 py-3">
                            <div class="flex items-center justify-center">
                                <div class="w-20 bg-gray-100 rounded-full h-2">
                                    <div class="h-2 rounded-full {{ $passRate >= 80 ? 'bg-emerald-500' : ($passRate >= 50 ? 'bg-amber-500' : 'bg-red-500') }}" style="width: {{ $passRate }}%"></div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Recent Payments --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-700">{{ __('Recent Payments') }}</h3>
            <a href="{{ route('admin.payments.index') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">{{ __('View All') }} &rarr;</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3 text-left">{{ __('Receipt') }}</th>
                        <th class="px-6 py-3 text-left">{{ __('Student') }}</th>
                        <th class="px-6 py-3 text-right">{{ __('Amount') }}</th>
                        <th class="px-6 py-3 text-center">{{ __('Method') }}</th>
                        <th class="px-6 py-3 text-left">{{ __('Date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($recentPayments as $p)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 font-mono text-xs text-gray-600">{{ $p->receipt_number }}</td>
                        <td class="px-6 py-3 font-medium text-gray-900">{{ $p->enrollment?->student?->full_name ?? 'N/A' }}</td>
                        <td class="px-6 py-3 text-right font-semibold text-gray-800">{{ number_format($p->amount) }} XAF</td>
                        <td class="px-6 py-3 text-center">
                            @php $methodColors = ['cash' => 'emerald', 'bank_transfer' => 'blue', 'mtn_momo' => 'yellow', 'orange_money' => 'orange']; @endphp
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $methodColors[$p->payment_method] ?? 'gray' }}-100 text-{{ $methodColors[$p->payment_method] ?? 'gray' }}-700">
                                {{ str_replace('_', ' ', ucfirst($p->payment_method)) }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-gray-500">{{ \Carbon\Carbon::parse($p->payment_date)->format('M d, Y') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-6 py-8 text-center text-gray-400">{{ __('No payments recorded yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
