@extends('layouts.admin')
@section('title', __('Add Fee Category'))

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('admin.fee-categories.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-2xl font-bold text-gray-800">{{ __('Add Fee Category') }}</h1>
    </div>

    <form action="{{ route('admin.fee-categories.store') }}" method="POST" class="bg-white rounded-xl shadow-sm border border-gray-200">
        @csrf
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('Category Name') }} *</label>
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="{{ __('e.g. Tuition Fee') }}" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('Code') }} *</label>
                <input type="text" name="code" value="{{ old('code') }}" required placeholder="{{ __('e.g. TUITION') }}" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 uppercase font-mono">
                @error('code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('Description') }}</label>
                <textarea name="description" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">{{ old('description') }}</textarea>
            </div>
            <div class="flex items-center gap-8 flex-wrap">
                <label class="flex items-center gap-2">
                    <input type="hidden" name="is_mandatory" value="0">
                    <input type="checkbox" name="is_mandatory" value="1" {{ old('is_mandatory', true) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm text-gray-700">{{ __('Mandatory for all students') }}</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="hidden" name="is_refundable" value="0">
                    <input type="checkbox" name="is_refundable" value="1" {{ old('is_refundable') ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm text-gray-700">{{ __('Refundable') }}</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="hidden" name="is_tuition" value="0">
                    <input type="checkbox" name="is_tuition" value="1" {{ old('is_tuition') ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm text-gray-700">{{ __('Is Tuition') }}</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="hidden" name="is_boarding" value="0">
                    <input type="checkbox" name="is_boarding" value="1" {{ old('is_boarding') ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm text-gray-700">{{ __('Is Boarding') }}</span>
                </label>
            </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 border-t rounded-b-xl flex justify-end gap-3">
            <a href="{{ route('admin.fee-categories.index') }}" class="px-4 py-2 text-gray-600 hover:text-gray-800 text-sm font-medium">{{ __('Cancel') }}</a>
            <button type="submit" class="px-6 py-2.5 bg-[#1e293b] text-white rounded-lg hover:bg-[#334155] text-sm font-medium">{{ __('Create Category') }}</button>
        </div>
    </form>
</div>
@endsection
