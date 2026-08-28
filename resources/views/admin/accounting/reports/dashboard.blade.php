@extends('layouts.admin')

@section('title', __('Accounting Reports'))
@section('breadcrumb', __('Accounting > Reports'))

@section('content')
@php
    $currency = \App\Models\SchoolSetting::current()?->currency ?? 'FCFA';

    $reports = [
        [
            'route' => 'admin.accounting-reports.student-fee-aging',
            'title' => __('Student Fee Aging'),
            'blurb' => __('Who owes school fees, and for how long.'),
        ],
        [
            'route' => 'admin.accounting-reports.receivables-aging',
            'title' => __('Receivables Aging'),
            'blurb' => __('Money owed to the school, by age.'),
        ],
        [
            'route' => 'admin.accounting-reports.payables-aging',
            'title' => __('Payables Aging'),
            'blurb' => __('Money the school owes, by age.'),
        ],
        [
            'route' => 'admin.accounting-reports.budget-vs-actual',
            'title' => __('Budget vs Actual'),
            'blurb' => __('Planned spend against what was actually spent.'),
        ],
        [
            'route' => 'admin.accounting-reports.trial-balance',
            'title' => __('Trial Balance'),
            'blurb' => __('Debits and credits across every account.'),
        ],
        [
            'route' => 'admin.accounting-reports.balance-sheet',
            'title' => __('Balance Sheet'),
            'blurb' => __('Assets against liabilities and equity.'),
        ],
        [
            'route' => 'admin.accounting-reports.income-statement',
            'title' => __('Income Statement'),
            'blurb' => __('Revenue against expenses for the period.'),
        ],
    ];
@endphp

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('Accounting Reports') }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ __('As of') }} {{ $asOf->format('d/m/Y') }}</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Outstanding Fees') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($outstandingFees, 0) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $currency }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Students Owing') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($studentsOwing) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Receivables') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($receivables, 0) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ __('posted ledger') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Payables') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($payables, 0) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ __('posted ledger') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($reports as $report)
        <a href="{{ route($report['route']) }}"
           class="block bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:border-slate-400 hover:shadow transition">
            <h3 class="font-semibold text-gray-900">{{ $report['title'] }}</h3>
            <p class="mt-1 text-sm text-gray-500">{{ $report['blurb'] }}</p>
        </a>
        @endforeach
    </div>
</div>
@endsection
