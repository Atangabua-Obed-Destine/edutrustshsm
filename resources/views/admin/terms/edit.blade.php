@extends('layouts.admin')

@section('title', __('Edit Term'))
@section('breadcrumb', __('Academic > Terms > Edit'))

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-700 mb-6">{{ __('Edit Term') }}: {{ $term->name }}</h3>

        <form method="POST" action="{{ route('admin.terms.update', $term) }}">
            @csrf @method('PUT')

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Term Number') }}</label>
                        <select name="term_number" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" required>
                            <option value="1" {{ old('term_number', $term->term_number) == 1 ? 'selected' : '' }}>{{ __('Term 1') }}</option>
                            <option value="2" {{ old('term_number', $term->term_number) == 2 ? 'selected' : '' }}>{{ __('Term 2') }}</option>
                            <option value="3" {{ old('term_number', $term->term_number) == 3 ? 'selected' : '' }}>{{ __('Term 3') }}</option>
                        </select>
                        @error('term_number') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Term Name') }}</label>
                        <input type="text" name="name" value="{{ old('name', $term->name) }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" required>
                        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Start Date') }}</label>
                        <input type="date" name="start_date" value="{{ old('start_date', $term->start_date->format('Y-m-d')) }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" required>
                        @error('start_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('End Date') }}</label>
                        <input type="date" name="end_date" value="{{ old('end_date', $term->end_date->format('Y-m-d')) }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" required>
                        @error('end_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="hidden" name="is_current" value="0">
                        <input type="checkbox" name="is_current" value="1" {{ old('is_current', $term->is_current) ? 'checked' : '' }}
                               class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm font-medium text-gray-700">{{ __('Set as Current Term') }}</span>
                    </label>
                    <p class="text-xs text-gray-400 mt-1 ml-7">{{ __('Only one term can be current at a time. Enabling this will deactivate any other current term.') }}</p>
                </div>
            </div>

            <div class="flex items-center justify-end mt-6 space-x-3">
                <a href="{{ route('admin.terms.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">{{ __('Cancel') }}</a>
                <button type="submit" class="bg-[#1e293b] hover:bg-[#334155] text-white px-6 py-2 rounded-lg text-sm font-medium transition">
                    {{ __('Update Term') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
