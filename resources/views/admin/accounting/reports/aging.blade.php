@extends('layouts.admin')

@section('title', $title)
@section('breadcrumb', __('Accounting > Reports') . ' > ' . $title)

@section('content')
@php
    $currency = \App\Models\SchoolSetting::current()?->currency ?? 'FCFA';
@endphp

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ __('Outstanding balances grouped by how long they have been due.') }}
            </p>
        </div>
        <a href="{{ route('admin.accounting-reports.index') }}" class="text-sm text-blue-600 hover:underline">
            {{ __('Back to Reports') }}
        </a>
    </div>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('As of') }}</label>
                <input type="date" name="as_of" value="{{ $asOf }}" class="rounded-lg border-gray-300 text-sm">
            </div>
            @if(($kind ?? null) === 'student' && isset($forms))
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Form / Class') }}</label>
                <select name="form_id" class="rounded-lg border-gray-300 text-sm">
                    <option value="">{{ __('All forms') }}</option>
                    @foreach($forms as $form)
                    <option value="{{ $form->id }}" @selected(request('form_id') == $form->id)>{{ $form->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-lg text-sm font-medium hover:bg-slate-700">
                {{ __('Filter') }}
            </button>
        </div>
    </form>

    {{-- Bucket summary --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        @foreach($buckets as $bucket)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">
                {{ $bucket }} {{ __('days') }}
            </p>
            <p class="text-lg font-bold mt-1 {{ str_ends_with($bucket, '+') && ($totals[$bucket] ?? 0) > 0 ? 'text-red-600' : 'text-gray-900' }}">
                {{ number_format($totals[$bucket] ?? 0, 0) }}
            </p>
        </div>
        @endforeach
        <div class="bg-slate-800 rounded-xl shadow-sm p-4 text-center">
            <p class="text-xs font-medium text-slate-300 uppercase tracking-wider">{{ __('Total') }}</p>
            <p class="text-lg font-bold text-white mt-1">{{ number_format($totals['total'] ?? 0, 0) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-3 text-left">{{ $subject }}</th>
                    @if(($kind ?? null) === 'student')
                    <th class="px-6 py-3 text-left">{{ __('Class') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Fee') }}</th>
                    @endif
                    <th class="px-6 py-3 text-left">{{ ($kind ?? null) === 'student' ? __('Due') : __('Oldest entry') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Days') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Bucket') }}</th>
                    <th class="px-6 py-3 text-right">{{ __('Balance') }} ({{ $currency }})</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rows as $row)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 font-medium text-gray-900">
                        @if(($kind ?? null) === 'student')
                            {{ $row->student?->first_name }} {{ $row->student?->last_name }}
                            <span class="text-gray-400 text-xs">{{ $row->student?->student_id }}</span>
                        @else
                            <span class="text-gray-400">{{ $row->account->account_code }}</span>
                            {{ $row->account->account_name }}
                        @endif
                    </td>
                    @if(($kind ?? null) === 'student')
                    <td class="px-6 py-3 text-gray-600">{{ $row->class ?? '—' }}</td>
                    <td class="px-6 py-3 text-gray-600">{{ $row->category ?? '—' }}</td>
                    @endif
                    <td class="px-6 py-3 text-gray-600">
                        @php $d = ($kind ?? null) === 'student' ? $row->due_date : $row->oldest_entry; @endphp
                        {{ $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '—' }}
                    </td>
                    <td class="px-6 py-3 text-center {{ $row->days_overdue > 90 ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
                        {{ $row->days_overdue }}
                    </td>
                    <td class="px-6 py-3 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold
                            {{ str_ends_with($row->bucket, '+') ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700' }}">
                            {{ $row->bucket }}
                        </span>
                    </td>
                    <td class="px-6 py-3 text-right font-medium text-gray-900">{{ number_format($row->balance, 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ ($kind ?? null) === 'student' ? 7 : 5 }}" class="px-6 py-12 text-center text-gray-500">
                        {{ __('Nothing outstanding.') }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
