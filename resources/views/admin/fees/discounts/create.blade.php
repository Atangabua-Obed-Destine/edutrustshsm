@extends('layouts.admin')
@section('title', __('Apply Discount'))

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('admin.fee-discounts.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-2xl font-bold text-gray-800">{{ __('Apply Discount / Waiver') }}</h1>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
            <ul class="list-disc list-inside text-sm">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form action="{{ route('admin.fee-discounts.store') }}" method="POST" class="bg-white rounded-xl shadow-sm border border-gray-200">
        @csrf
        <div class="p-6 space-y-5">
            {{-- Student --}}
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('Student') }} *</label>
                <select name="student_enrollment_id" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">{{ __('Select a student...') }}</option>
                    @foreach($enrollments as $e)
                        <option value="{{ $e->id }}" {{ old('student_enrollment_id') == $e->id ? 'selected' : '' }}>
                            {{ $e->student->student_id }} — {{ $e->student->full_name }} ({{ $e->classSection->name ?? __('N/A') }})
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Reason --}}
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('Reason') }} *</label>
                <select name="reason" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">{{ __('Select reason...') }}</option>
                    <option value="scholarship" {{ old('reason') == 'scholarship' ? 'selected' : '' }}>{{ __('Scholarship') }}</option>
                    <option value="staff_child" {{ old('reason') == 'staff_child' ? 'selected' : '' }}>{{ __('Staff Child') }}</option>
                    <option value="sibling" {{ old('reason') == 'sibling' ? 'selected' : '' }}>{{ __('Sibling Discount') }}</option>
                    <option value="financial_hardship" {{ old('reason') == 'financial_hardship' ? 'selected' : '' }}>{{ __('Financial Hardship') }}</option>
                    <option value="merit" {{ old('reason') == 'merit' ? 'selected' : '' }}>{{ __('Academic Merit') }}</option>
                    <option value="other" {{ old('reason') == 'other' ? 'selected' : '' }}>{{ __('Other') }}</option>
                </select>
            </div>

            {{-- Type & Value --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('Discount Type') }} *</label>
                    <select name="discount_type" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="percentage" {{ old('discount_type') == 'percentage' ? 'selected' : '' }}>{{ __('Percentage (%)') }}</option>
                        <option value="fixed" {{ old('discount_type') == 'fixed' ? 'selected' : '' }}>{{ __('Fixed Amount (XAF)') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('Value') }} *</label>
                    <input type="number" step="0.01" name="value" value="{{ old('value') }}" required min="0.01" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="{{ __('e.g. 25 for 25% or 50000 for XAF') }}">
                </div>
            </div>

            {{-- Apply To --}}
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('Apply To') }} *</label>
                <div class="mt-2 space-y-2">
                    <label class="flex items-center gap-2">
                        <input type="radio" name="apply_to" value="all_fees" {{ old('apply_to', 'all_fees') == 'all_fees' ? 'checked' : '' }} class="text-blue-600 focus:ring-blue-500" onchange="document.getElementById('category-select').classList.add('hidden')">
                        <span class="text-sm text-gray-700">{{ __('All fee categories') }}</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="apply_to" value="specific_category" {{ old('apply_to') == 'specific_category' ? 'checked' : '' }} class="text-blue-600 focus:ring-blue-500" onchange="document.getElementById('category-select').classList.remove('hidden')">
                        <span class="text-sm text-gray-700">{{ __('Specific category only') }}</span>
                    </label>
                </div>
                <div id="category-select" class="{{ old('apply_to') == 'specific_category' ? '' : 'hidden' }} mt-3">
                    <select name="fee_category_id" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">{{ __('Select category...') }}</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('fee_category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Description --}}
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('Description / Notes') }}</label>
                <textarea name="description" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="{{ __('Additional details about this discount...') }}">{{ old('description') }}</textarea>
            </div>
        </div>

        <div class="px-6 py-4 bg-gray-50 border-t rounded-b-xl flex justify-end gap-3">
            <a href="{{ route('admin.fee-discounts.index') }}" class="px-4 py-2 text-gray-600 hover:text-gray-800 text-sm font-medium">{{ __('Cancel') }}</a>
            <button type="submit" class="px-6 py-2.5 bg-[#1e293b] text-white rounded-lg hover:bg-[#334155] text-sm font-medium">{{ __('Apply Discount') }}</button>
        </div>
    </form>
</div>
@endsection
