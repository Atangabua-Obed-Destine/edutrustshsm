@extends('layouts.admin')

@section('title', __('Tax Remittances'))
@section('breadcrumb', __('HR > Tax Remittances'))

@section('content')
@php
    $currency = \App\Models\SchoolSetting::current()?->currency ?? 'FCFA';
    $money = fn ($v) => number_format((float) $v, 0, '.', ' ');
    $tones = [
        'undeclared' => 'bg-red-100 text-red-700',
        'part_paid' => 'bg-amber-100 text-amber-800',
        'settled' => 'bg-emerald-100 text-emerald-800',
        'overpaid' => 'bg-blue-100 text-blue-800',
    ];
    $labels = [
        'undeclared' => __('Not paid'),
        'part_paid' => __('Part paid'),
        'settled' => __('Settled'),
        'overpaid' => __('Overpaid'),
    ];
@endphp

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('Tax Remittances') }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ __('Payroll tax the school has withheld and holds on behalf of the tax office and the CNPS, month by month, and the record of paying it over.') }}</p>
    </div>

    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-sm text-emerald-700 font-medium">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700 font-medium">{{ session('error') }}</div>
    @endif
    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <ul class="text-sm text-red-700 list-disc list-inside">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Total still owed') }}</p>
            <p class="text-2xl font-bold {{ $totalOwing > 0 ? 'text-red-600' : 'text-gray-300' }} mt-1">{{ $money($totalOwing) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $currency }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Oldest month owing') }}</p>
            @if($oldestOwing)
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $oldestOwing['month_label'] }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $oldestOwing['account_code'] }} — {{ $money($oldestOwing['outstanding']) }} {{ $currency }}</p>
            @else
            <p class="text-2xl font-bold text-gray-300 mt-1">—</p>
            @endif
        </div>
    </div>

    @foreach($reconciliation as $check)
    @unless($check['agrees'])
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
        {{ __('Account :code carries :ledger in the ledger, but the months below add up to :expected. Something other than payroll or a remittance has been posted to it — usually a hand-typed journal entry.', [
            'code' => $check['account_code'].' '.$check['account_name'],
            'ledger' => $money($check['ledger']),
            'expected' => $money($check['per_month']),
        ]) }}
    </div>
    @endunless
    @endforeach

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-5 py-3 text-left">{{ __('Salary month') }}</th>
                    <th class="px-5 py-3 text-left">{{ __('Authority account') }}</th>
                    <th class="px-5 py-3 text-right">{{ __('Employee') }}</th>
                    <th class="px-5 py-3 text-right">{{ __('Employer') }}</th>
                    <th class="px-5 py-3 text-right">{{ __('Withheld') }}</th>
                    <th class="px-5 py-3 text-right">{{ __('Paid') }}</th>
                    <th class="px-5 py-3 text-right">{{ __('Outstanding') }}</th>
                    <th class="px-5 py-3 text-center">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rows as $row)
                <tr class="hover:bg-gray-50 align-top">
                    <td class="px-5 py-3 font-medium text-gray-900">
                        {{ $row['month_label'] }}
                        @if($row['staff_count'])<span class="block text-xs text-gray-400">{{ trans_choice(':count payslip|:count payslips', $row['staff_count'], ['count' => $row['staff_count']]) }}</span>@endif
                    </td>
                    <td class="px-5 py-3 text-gray-600"><span class="font-mono text-gray-400">{{ $row['account_code'] }}</span> {{ $row['account_name'] }}</td>
                    <td class="px-5 py-3 text-right font-mono text-gray-500">{{ $money($row['employee']) }}</td>
                    <td class="px-5 py-3 text-right font-mono text-gray-500">{{ $money($row['employer']) }}</td>
                    <td class="px-5 py-3 text-right font-mono">{{ $money($row['due']) }}</td>
                    <td class="px-5 py-3 text-right font-mono">{{ $money($row['paid']) }}</td>
                    <td class="px-5 py-3 text-right font-mono font-semibold {{ $row['outstanding'] > 0.005 ? 'text-red-600' : 'text-gray-400' }}">{{ $money($row['outstanding']) }}</td>
                    <td class="px-5 py-3 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $tones[$row['status']] }}">{{ $labels[$row['status']] }}</span>
                    </td>
                </tr>
                @if($row['outstanding'] > 0.005)
                @can('tax-remittance.create')
                <tr>
                    <td colspan="8" class="px-5 pb-4">
                        <details>
                            <summary class="text-sm text-blue-600 cursor-pointer">{{ __('Record payment for :month', ['month' => $row['month_label']]) }}</summary>
                            <form method="POST" action="{{ route('admin.tax-remittances.store') }}" class="mt-3 grid grid-cols-1 md:grid-cols-6 gap-3 bg-gray-50 rounded-lg p-3">
                                @csrf
                                <input type="hidden" name="liability_account_id" value="{{ $row['account_id'] }}">
                                <input type="hidden" name="salary_month" value="{{ $row['month'] }}">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Amount') }}</label>
                                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ $row['outstanding'] }}" required class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Payment date') }}</label>
                                    <input type="date" name="payment_date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Paid from (ledger)') }}</label>
                                    <select name="source_account_id" required style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                                        @foreach($sourceAccounts as $account)<option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->account_name }}</option>@endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Payment account') }}</label>
                                    <select name="payment_account_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                                        <option value="">{{ __('Not from a payment account') }}</option>
                                        @foreach($paymentAccounts as $account)<option value="{{ $account->id }}">{{ $account->title }} ({{ $money($account->current_balance) }})</option>@endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Receipt / declaration no.') }}</label>
                                    <input type="text" name="reference" maxlength="191" class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                                </div>
                                <div class="flex items-end">
                                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium">{{ __('Record and post') }}</button>
                                </div>
                                <div class="md:col-span-6 flex flex-wrap gap-4 text-xs text-gray-600">
                                    <label class="flex items-center gap-1"><input type="checkbox" name="allow_additional" value="1" class="rounded"> {{ __('This is an additional payment for a month already paid') }}</label>
                                    <label class="flex items-center gap-1"><input type="checkbox" name="allow_overpayment" value="1" class="rounded"> {{ __('Paying more than was withheld is intended (penalty or adjustment)') }}</label>
                                </div>
                            </form>
                        </details>
                    </td>
                </tr>
                @endcan
                @endif
                @empty
                <tr><td colspan="8" class="px-5 py-12 text-center text-gray-400">{{ __('No payroll tax has been withheld yet. Months appear here once payslips are paid.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <h2 class="text-sm font-semibold text-gray-700 px-5 pt-5">{{ __('Payments recorded') }}</h2>
        <table class="w-full text-sm mt-3">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-5 py-3 text-left">{{ __('Paid on') }}</th>
                    <th class="px-5 py-3 text-left">{{ __('Salary month') }}</th>
                    <th class="px-5 py-3 text-left">{{ __('Authority account') }}</th>
                    <th class="px-5 py-3 text-right">{{ __('Amount') }}</th>
                    <th class="px-5 py-3 text-left">{{ __('Reference') }}</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($remittances as $remittance)
                <tr class="{{ $remittance->isVoided() ? 'text-gray-400' : '' }}">
                    <td class="px-5 py-3">{{ $remittance->payment_date->format('d/m/Y') }}</td>
                    <td class="px-5 py-3">{{ $monthLabel($remittance->salary_month) }}</td>
                    <td class="px-5 py-3">{{ $remittance->liabilityAccount?->account_code }} {{ $remittance->liabilityAccount?->account_name }}</td>
                    <td class="px-5 py-3 text-right font-mono {{ $remittance->isVoided() ? 'line-through' : '' }}">{{ $money($remittance->amount) }}</td>
                    <td class="px-5 py-3">
                        {{ $remittance->reference ?? '—' }}
                        <span class="block text-xs text-gray-400">{{ $remittance->sourceAccount?->account_code }}@if($remittance->paymentAccount) · {{ $remittance->paymentAccount->title }}@endif</span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        @if($remittance->isVoided())
                            <span class="text-xs">{{ __('Voided :date', ['date' => $remittance->voided_at->format('d/m/Y')]) }}</span>
                            @if($remittance->void_reason)<span class="block text-xs">{{ $remittance->void_reason }}</span>@endif
                        @else
                            @can('tax-remittance.void')
                            <form method="POST" action="{{ route('admin.tax-remittances.void', $remittance) }}" class="flex justify-end gap-2">@csrf
                                <input type="text" name="void_reason" required minlength="5" maxlength="500" placeholder="{{ __('Why void it?') }}" class="px-2 py-1 border border-gray-300 rounded-lg text-xs">
                                <button type="submit" class="text-red-600 hover:underline text-xs" onclick="return confirm('{{ __('Void this payment? A reversing entry will be posted.') }}')">{{ __('Void') }}</button>
                            </form>
                            @endcan
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-5 py-8 text-center text-gray-400">{{ __('No remittances recorded yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
