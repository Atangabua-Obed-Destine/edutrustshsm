@extends('layouts.admin')

@section('title', __('Edit Class Section'))
@section('breadcrumb', __('Academic > Class Sections > Edit'))

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-700 mb-6">{{ __('Edit:') }} {{ $classSection->name }}</h3>

        <form method="POST" action="{{ route('admin.class-sections.update', $classSection) }}">
            @csrf @method('PUT')

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Form') }}</label>
                        <select name="form_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" required>
                            @foreach($forms as $form)
                                <option value="{{ $form->id }}" {{ old('form_id', $classSection->form_id) == $form->id ? 'selected' : '' }}>{{ $form->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Section Letter') }}</label>
                        <input type="text" name="section" value="{{ old('section', $classSection->section) }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" maxlength="5" required>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Display Name') }}</label>
                    <input type="text" name="name" value="{{ old('name', $classSection->name) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" required>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Class Teacher') }}</label>
                        <select name="class_teacher_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            <option value="">{{ __('Not Assigned') }}</option>
                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}" {{ old('class_teacher_id', $classSection->class_teacher_id) == $teacher->id ? 'selected' : '' }}>{{ $teacher->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Room') }}</label>
                        <select name="room_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            <option value="">{{ __('Not Assigned') }}</option>
                            @foreach($rooms as $room)
                                <option value="{{ $room->id }}" {{ old('room_id', $classSection->room_id) == $room->id ? 'selected' : '' }}>{{ $room->name }} ({{ $room->capacity }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Max Students') }}</label>
                    <input type="number" name="max_students" value="{{ old('max_students', $classSection->max_students) }}" min="1" max="200"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" required>
                </div>
            </div>

            <div class="flex items-center justify-end mt-6 space-x-3">
                <a href="{{ route('admin.class-sections.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">{{ __('Cancel') }}</a>
                <button type="submit" class="bg-[#1e293b] hover:bg-[#334155] text-white px-6 py-2 rounded-lg text-sm font-medium transition">
                    {{ __('Update Class Section') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
