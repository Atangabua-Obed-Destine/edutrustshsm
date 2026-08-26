@extends('layouts.admin')

@section('title', __('General Ledger'))
@section('breadcrumb', __('Accounting > Reports > General Ledger'))

@section('content')
<div class="space-y-5">
    <h3 class="text-lg font-semibold text-gray-700">{{ __('General Ledger') }} <span class="text-sm text-gray-400">(Grand Livre)</span></h3>

    <div>
        <p class="text-sm font-semibold text-gray-600 mb-2">{{ __('Quick Access') }}</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="{{ route('admin.accounting-reports.trial-balance') }}" class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow"><p class="font-semibold text-gray-800">{{ __('Trial Balance') }}</p><p class="text-sm text-blue-600 mt-2">{{ __('View Report →') }}</p></a>
            <a href="{{ route('admin.accounting-reports.income-statement') }}" class="rounded-xl p-5 text-white hover:shadow" style="background:#16794a;"><p class="font-semibold">{{ __('Income Statement') }}</p><p class="text-xs opacity-90">Compte de Résultat</p><p class="text-sm mt-2">{{ __('View Report →') }}</p></a>
            <a href="{{ route('admin.accounting-reports.balance-sheet') }}" class="rounded-xl p-5 text-white hover:shadow" style="background:#f1a911;"><p class="font-semibold">{{ __('Balance Sheet') }}</p><p class="text-xs opacity-90">Bilan</p><p class="text-sm mt-2">{{ __('View Report →') }}</p></a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <p class="text-sm font-semibold text-gray-600 mb-3">{{ __('Account Ledger') }}</p>
        <form method="GET" action="{{ route('admin.accounting-reports.account-ledger') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('Select Account') }}</label>
                <select name="account_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                    <option value="">{{ __('Select Account') }}</option>
                    @foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->account_code }} — {{ $a->account_name }}</option>@endforeach
                </select>
            </div>
            <div><label class="block text-xs text-gray-500 mb-1">{{ __('Start Date') }}</label><input type="date" name="start_date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
            <div><label class="block text-xs text-gray-500 mb-1">{{ __('End Date') }}</label><input type="date" name="end_date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('View') }}</button>
        </form>
    </div>
</div>
@endsection
