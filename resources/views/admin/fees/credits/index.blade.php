@extends('layouts.admin')

@section('title', __('Student Credits'))
@section('breadcrumb', __('Fees > Student Credits'))

@section('content')
@php
    $currency = \App\Models\SchoolSetting::current()?->currency ?? 'FCFA';
@endphp

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Student Credits') }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ __('Money paid beyond what a student owed. The school holds it on their behalf until it is applied to a fee.') }}
            </p>
        </div>
        <a href="{{ route('admin.student-credits.index', ['only_available' => request('only_available') ? null : 1]) }}"
           class="text-sm text-blue-600 hover:underline">
            {{ request('only_available') ? __('Show all') : __('Show only available') }}
        </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Total Available') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($totalAvailable, 0) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $currency }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Students Holding Credit') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($studentsHolding) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-3 text-left">{{ __('Student') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Class') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Source') }}</th>
                    <th class="px-6 py-3 text-right">{{ __('Amount') }}</th>
                    <th class="px-6 py-3 text-right">{{ __('Used') }}</th>
                    <th class="px-6 py-3 text-right">{{ __('Available') }} ({{ $currency }})</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($credits as $credit)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 font-medium text-gray-900">
                        {{ $credit->enrollment?->student?->first_name }} {{ $credit->enrollment?->student?->last_name }}
                        <span class="text-gray-400 text-xs ml-1">{{ $credit->enrollment?->student?->student_id }}</span>
                    </td>
                    <td class="px-6 py-3 text-gray-600">{{ $credit->enrollment?->classSection?->name ?? '—' }}</td>
                    <td class="px-6 py-3 text-gray-600">
                        {{ __(ucfirst(str_replace('_', ' ', $credit->source))) }}
                        @if($credit->payment)
                        <span class="text-gray-400 text-xs block">{{ $credit->payment->receipt_number }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-right text-gray-600">{{ number_format((float) $credit->amount, 2) }}</td>
                    <td class="px-6 py-3 text-right text-gray-600">{{ number_format((float) $credit->used_amount, 2) }}</td>
                    <td class="px-6 py-3 text-right font-semibold {{ (float) $credit->balance > 0 ? 'text-emerald-700' : 'text-gray-400' }}">
                        {{ number_format((float) $credit->balance, 2) }}
                    </td>
                    <td class="px-6 py-3 text-right">
                        @if((float) $credit->balance > 0)
                        @can('student-credit.apply')
                        <form method="POST" action="{{ route('admin.student-credits.apply') }}" class="inline"
                              onsubmit="return confirm('{{ __('Apply this credit to the student\'s outstanding fees?') }}')">
                            @csrf
                            <input type="hidden" name="student_enrollment_id" value="{{ $credit->student_enrollment_id }}">
                            <button type="submit" class="text-blue-600 hover:underline">{{ __('Apply to fees') }}</button>
                        </form>
                        @endcan
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        {{ __('No student credits. Over-payments appear here automatically.') }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $credits->links() }}
</div>
@endsection
