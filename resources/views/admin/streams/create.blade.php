@extends('layouts.admin')

@section('title', __('Add Stream'))
@section('breadcrumb', __('Academic > Streams > Create'))

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-700 mb-6">{{ __('New Stream') }}</h3>

        <form method="POST" action="{{ route('admin.streams.store') }}">
            @csrf

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Stream Name') }}</label>
                        <input type="text" name="name" value="{{ old('name') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                               placeholder="{{ __('e.g., Science') }}" required>
                        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Code') }}</label>
                        <input type="text" name="code" value="{{ old('code') }}" maxlength="10"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none uppercase font-mono"
                               placeholder="{{ __('e.g., SCI') }}" required>
                        @error('code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="flex items-center">
                        <input type="hidden" name="is_general" value="0">
                        <input type="checkbox" name="is_general" value="1" {{ old('is_general') ? 'checked' : '' }}
                               class="rounded border-gray-300 text-blue-600">
                        <span class="ml-2 text-sm text-gray-700">{{ __('General stream') }}</span>
                        <span class="ml-1 text-xs text-gray-400">{{ __('(all subjects are compulsory)') }}</span>
                    </label>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Description') }} <span class="text-gray-400">{{ __('(optional)') }}</span></label>
                    <textarea name="description" rows="3"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                              placeholder="{{ __('Brief description of this stream...') }}">{{ old('description') }}</textarea>
                    @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t">
                <a href="{{ route('admin.streams.index') }}" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition text-sm">{{ __('Cancel') }}</a>
                <button type="submit" class="px-6 py-2 bg-[#1e293b] text-white rounded-lg hover:bg-[#334155] transition text-sm font-medium">{{ __('Create Stream') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
