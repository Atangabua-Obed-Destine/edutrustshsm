@extends('layouts.admin')

@section('title', __('Withdraw'))
@section('breadcrumb', __('Payment Accounts > Withdraw'))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'CFA')

@section('content')
<div class="max-w-2xl bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-700 mb-1">{{ __('Withdraw from') }}: {{ $account->title }}</h3>
    <p class="text-sm text-gray-500 mb-6">{{ __('Current balance') }}: <span class="font-semibold text-amber-700">{{ number_format($account->current_balance, 2) }} {{ $currency }}</span></p>

    <form method="POST" action="{{ route('admin.payment-account.withdraw', $account) }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Amount') }} <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-blue-500" required>
                @error('amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Date') }} <span class="text-red-500">*</span></label>
                <input type="date" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Title') }}</label>
                <input type="text" name="title" value="{{ old('title') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Reference') }}</label>
                <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Description') }}</label>
            <textarea name="description" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">{{ old('description') }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Attachment') }}</label>
            <input type="file" name="attach" class="w-full text-sm text-gray-600">
        </div>
        <div class="flex justify-end space-x-3">
            <a href="{{ route('admin.payment-account.account-book', $account) }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('Cancel') }}</a>
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white px-6 py-2 rounded-lg text-sm font-medium">{{ __('Withdraw') }}</button>
        </div>
    </form>
</div>
@endsection
