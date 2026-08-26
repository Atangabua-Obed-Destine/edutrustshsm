@extends('layouts.admin')

@section('title', __('Fund Transfer'))
@section('breadcrumb', __('Payment Accounts > Fund Transfer'))

@section('content')
<div class="max-w-3xl bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-700 mb-4">{{ __('Fund Transfer') }}</h3>

    <div class="bg-blue-50 text-blue-800 text-sm px-4 py-3 rounded-lg mb-6">
        {{ __('Use this to move money between your accounts. This will debit the source account and credit the destination account automatically.') }}
    </div>

    <form method="POST" action="{{ route('admin.payment-account-transfer.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('From Account') }} <span class="text-red-500">*</span></label>
                <select name="from_account_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-blue-500" required>
                    <option value="">{{ __('Select') }}</option>
                    @foreach($accounts as $a)
                        <option value="{{ $a->id }}" {{ old('from_account_id') == $a->id ? 'selected' : '' }}>{{ $a->title }} ({{ number_format($a->current_balance, 2) }})</option>
                    @endforeach
                </select>
                @error('from_account_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('To Account') }} <span class="text-red-500">*</span></label>
                <select name="to_account_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-blue-500" required>
                    <option value="">{{ __('Select') }}</option>
                    @foreach($accounts as $a)
                        <option value="{{ $a->id }}" {{ old('to_account_id') == $a->id ? 'selected' : '' }}>{{ $a->title }} ({{ number_format($a->current_balance, 2) }})</option>
                    @endforeach
                </select>
                @error('to_account_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Amount') }} <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-blue-500" required>
                @error('amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Transfer Date') }} <span class="text-red-500">*</span></label>
                <input type="date" name="transfer_date" value="{{ old('transfer_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Note') }}</label>
            <textarea name="note" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">{{ old('note') }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Attachment') }}</label>
            <input type="file" name="attach" class="w-full text-sm text-gray-600">
            <p class="text-xs text-gray-400 mt-1">{{ __('Allowed: JPG, PNG, PDF (Max 20MB)') }}</p>
        </div>
        <div class="flex justify-end space-x-3">
            <a href="{{ route('admin.payment-account-transfer.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('Cancel') }}</a>
            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg text-sm font-medium">{{ __('Transfer Funds') }}</button>
        </div>
    </form>
</div>
@endsection
