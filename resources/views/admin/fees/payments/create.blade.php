@extends('layouts.admin')

@section('title', __('Record Payment'))
@section('breadcrumb', __('Fees > Payments > Record'))

@section('content')
<div class="max-w-3xl">

    {{-- Student Search --}}
    @if(!$student)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-700 mb-4">{{ __('Find Student') }}</h3>
        <form method="GET" action="{{ route('admin.payments.create') }}" id="searchForm">
            <div class="flex gap-3">
                <input type="text" id="student_search" placeholder="{{ __('Enter Student ID (e.g.') }} {{ \App\Models\SchoolSetting::current()->student_id_prefix ?? 'SCH' }}/{{ date('Y') }}/001)"
                       class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <input type="hidden" name="student_id" id="student_id_field">
                <button type="button" onclick="searchStudent()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">{{ __('Find') }}</button>
            </div>
            <p class="text-xs text-gray-400 mt-2">{{ __('Search by student database ID number.') }}</p>
        </form>
    </div>

    <script>
    function searchStudent() {
        const val = document.getElementById('student_search').value.trim();
        if (val) {
            document.getElementById('student_id_field').value = val;
            document.getElementById('searchForm').submit();
        }
    }
    </script>

    @else

    {{-- Student Found - Payment Form --}}
    <form method="POST" action="{{ route('admin.payments.store') }}">
        @csrf
        <input type="hidden" name="student_enrollment_id" value="{{ $enrollment?->id }}">

        @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">
            {{ __('Please fix the errors below.') }}
        </div>
        @endif

        {{-- Student Info --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-semibold text-gray-700">{{ __('Student Details') }}</h3>
                <a href="{{ route('admin.payments.create') }}" class="text-sm text-blue-600 hover:underline">{{ __('Change Student') }}</a>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                <div>
                    <p class="text-xs text-gray-500">{{ __('Student ID') }}</p>
                    <p class="font-mono font-medium">{{ $student->student_id }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">{{ __('Name') }}</p>
                    <p class="font-medium">{{ $student->full_name }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">{{ __('Class') }}</p>
                    <p class="font-medium">{{ $enrollment?->classSection?->name ?? __('Not enrolled') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">{{ __('Residence') }}</p>
                    <p class="font-medium capitalize">{{ str_replace('_', ' ', $enrollment?->residence_type ?? '—') }}</p>
                </div>
            </div>
        </div>

        @if(!$enrollment)
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-lg text-sm">
            {{ __('Student is not enrolled in the current session. Cannot record payment.') }}
        </div>
        @else

        {{-- Outstanding Fees --}}
        @if($fees->count())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-4">
            <h4 class="font-semibold text-gray-700 mb-3">{{ __('Outstanding Fees') }}</h4>
            <table class="w-full text-sm">
                <thead class="text-xs text-gray-500 uppercase bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left">{{ __('Fee') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Net (XAF)') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Paid') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Balance') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($fees as $fee)
                    <tr>
                        <td class="px-3 py-2">{{ $fee->feeCategory->name }}</td>
                        <td class="px-3 py-2 text-right font-mono">{{ number_format($fee->net_amount) }}</td>
                        <td class="px-3 py-2 text-right font-mono text-green-600">{{ number_format($fee->paid_amount) }}</td>
                        <td class="px-3 py-2 text-right font-mono text-red-600 font-semibold">{{ number_format($fee->balance) }}</td>
                    </tr>
                    @endforeach
                    <tr class="bg-gray-50 font-semibold">
                        <td class="px-3 py-2">{{ __('Total') }}</td>
                        <td class="px-3 py-2 text-right font-mono">{{ number_format($fees->sum('net_amount')) }}</td>
                        <td class="px-3 py-2 text-right font-mono text-green-600">{{ number_format($fees->sum('paid_amount')) }}</td>
                        <td class="px-3 py-2 text-right font-mono text-red-600">{{ number_format($fees->sum('balance')) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        @else
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm mb-4">
            {{ __('All fees paid! No outstanding balance.') }}
        </div>
        @endif

        {{-- Payment Details --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-4">
            <h4 class="font-semibold text-gray-700 mb-4">{{ __('Payment Details') }}</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Amount (XAF)') }} <span class="text-red-500">*</span></label>
                    <input type="number" name="amount" value="{{ old('amount') }}" min="1" step="100"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg text-right font-mono focus:ring-2 focus:ring-blue-500 outline-none" required>
                    @error('amount') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Payment Date') }} <span class="text-red-500">*</span></label>
                    <input type="date" name="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Payment Method') }} <span class="text-red-500">*</span></label>
                    <select name="payment_method" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
                        <option value="cash" {{ old('payment_method') == 'cash' ? 'selected' : '' }}>{{ __('Cash') }}</option>
                        <option value="bank_transfer" {{ old('payment_method') == 'bank_transfer' ? 'selected' : '' }}>{{ __('Bank Transfer') }}</option>
                        <option value="mtn_momo" {{ old('payment_method') == 'mtn_momo' ? 'selected' : '' }}>{{ __('MTN MoMo') }}</option>
                        <option value="orange_money" {{ old('payment_method') == 'orange_money' ? 'selected' : '' }}>{{ __('Orange Money') }}</option>
                        <option value="edutrustpay" {{ old('payment_method') == 'edutrustpay' ? 'selected' : '' }}>{{ __('EduTrustPay') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Transaction Ref') }}</label>
                    <input type="text" name="transaction_ref" value="{{ old('transaction_ref') }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" placeholder="{{ __('MoMo ID / Bank ref') }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Payer Name') }}</label>
                    <input type="text" name="payer_name" value="{{ old('payer_name') }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Payer Phone') }}</label>
                    <input type="text" name="payer_phone" value="{{ old('payer_phone') }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Notes') }}</label>
                    <textarea name="notes" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end space-x-3">
            <a href="{{ route('admin.payments.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">{{ __('Cancel') }}</a>
            <button type="submit" class="bg-[#1e293b] hover:bg-[#334155] text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">
                {{ __('Record Payment') }}
            </button>
        </div>
        @endif
    </form>
    @endif
</div>
@endsection
