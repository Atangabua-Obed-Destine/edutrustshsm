@extends('layouts.admin')

@section('title', __('Account Mapping Settings'))
@section('breadcrumb', __('Accounting > Transaction Mappings'))

@php
    $acctOptions = function ($selected) use ($accounts) {
        $html = '<option value="">' . __('Select an account') . '</option>';
        foreach ($accounts as $a) {
            $sel = (string) $selected === (string) $a->id ? 'selected' : '';
            $html .= "<option value=\"{$a->id}\" {$sel}>{$a->account_code} — {$a->account_name}</option>";
        }
        return $html;
    };
@endphp

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-700">{{ __('Account Mapping Settings') }}</h3>
            <p class="text-sm text-gray-500">{{ __('Configure the debit/credit accounts used to auto-post each category.') }}</p>
        </div>
        <a href="{{ route('admin.account-mappings.unmapped') }}" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('View Unmapped') }}</a>
    </div>

    <div class="bg-blue-50 border border-blue-200 text-blue-800 text-sm rounded-lg px-4 py-3">
        {{ __('For each row pick a Debit and Credit account, then Save. New income/expense records and fee payments are posted to the ledger automatically using these rules.') }}
    </div>

    @php
        $sections = [
            ['fee_payment', __('Fee Payments'), collect([(object)['id' => null, 'title' => __('All Fee Payments (DR Cash → CR Fees/Revenue)')]]), 'bg-blue-600'],
            ['income', __('Income Categories'), $groups['income'], 'bg-green-600'],
            ['expense', __('Expense Categories'), $groups['expense'], 'bg-amber-500'],
        ];
    @endphp

    @foreach($sections as [$type, $heading, $cats, $color])
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="{{ $color }} text-white px-4 py-2.5 font-semibold text-sm">{{ $heading }}</div>
        <div class="divide-y divide-gray-100">
            @foreach($cats as $cat)
                @php($m = $mappings->get($type . ':' . ($cat->id ?? 'all')))
                <form method="POST" action="{{ route('admin.account-mappings.save') }}" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end px-4 py-3">
                    @csrf
                    <input type="hidden" name="mapping_type" value="{{ $type }}">
                    <input type="hidden" name="category_id" value="{{ $cat->id }}">
                    <div class="md:col-span-3"><span class="text-sm font-medium text-gray-700">{{ $cat->title }}</span></div>
                    <div class="md:col-span-4">
                        <label class="block text-xs text-gray-500 mb-1">{{ $type === 'expense' ? __('Debit (Expense Account)') : __('Debit (Payment / Asset)') }}</label>
                        <select name="debit_account_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">{!! $acctOptions($m?->debit_account_id) !!}</select>
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-xs text-gray-500 mb-1">{{ $type === 'expense' ? __('Credit (Payment / Asset)') : __('Credit (Revenue Account)') }}</label>
                        <select name="credit_account_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">{!! $acctOptions($m?->credit_account_id) !!}</select>
                    </div>
                    <div class="md:col-span-1">
                        <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white px-3 py-2 rounded-lg text-sm font-medium">{{ __('Save') }}</button>
                    </div>
                </form>
            @endforeach
        </div>
    </div>
    @endforeach
</div>
@endsection
