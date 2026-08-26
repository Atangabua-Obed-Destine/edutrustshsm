@extends('layouts.admin')

@section('title', __('Create Exam Sequence'))
@section('breadcrumb', __('Academic > Exam Sequences > Create'))

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-700 mb-6">{{ __('New Exam Sequence') }}</h3>

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm mb-6">
                {{ __('Please fix the errors below.') }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.sequences.store') }}">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Sequence Name') }} <span class="text-red-500">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" placeholder="{{ __('e.g., 1st Sequence') }}" required
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-end gap-3 mt-8 pt-6 border-t border-gray-200">
                <a href="{{ route('admin.sequences.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition">{{ __('Cancel') }}</a>
                <button type="submit" class="bg-[#1e293b] hover:bg-[#334155] text-white px-6 py-2 rounded-lg text-sm font-medium transition">{{ __('Create Sequence') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
