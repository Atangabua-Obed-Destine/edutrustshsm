@extends('layouts.admin')

@section('title', __('Edit Student'))
@section('breadcrumb', __('Students > Edit >') . ' ' . $student->student_id)

@section('content')
<div class="max-w-4xl">
    <form method="POST" action="{{ route('admin.students.update', $student) }}">
        @csrf @method('PUT')

        @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">
            {{ __('Please fix the errors below and try again.') }}
        </div>
        @endif

        {{-- Personal Information --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-4">
            <h3 class="text-lg font-semibold text-gray-700 mb-4 border-b pb-2">{{ __('Personal Information') }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Student ID') }}</label>
                    <input type="text" value="{{ $student->student_id }}" class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-500 cursor-not-allowed" disabled>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('First Name') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" value="{{ old('first_name', $student->first_name) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('first_name') border-red-500 @enderror" required>
                    @error('first_name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Last Name') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" value="{{ old('last_name', $student->last_name) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('last_name') border-red-500 @enderror" required>
                    @error('last_name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Other Names') }}</label>
                    <input type="text" name="other_names" value="{{ old('other_names', $student->other_names) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Date of Birth') }} <span class="text-red-500">*</span></label>
                    <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $student->date_of_birth->format('Y-m-d')) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Gender') }} <span class="text-red-500">*</span></label>
                    <select name="gender" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" required>
                        <option value="male" {{ old('gender', $student->gender) == 'male' ? 'selected' : '' }}>{{ __('Male') }}</option>
                        <option value="female" {{ old('gender', $student->gender) == 'female' ? 'selected' : '' }}>{{ __('Female') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Nationality') }}</label>
                    <input type="text" name="nationality" value="{{ old('nationality', $student->nationality) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Place of Birth') }}</label>
                    <input type="text" name="place_of_birth" value="{{ old('place_of_birth', $student->place_of_birth) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Religion') }}</label>
                    <select name="religion" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        <option value="">{{ __('Select') }}</option>
                        <option value="Christian" {{ old('religion', $student->religion) == 'Christian' ? 'selected' : '' }}>{{ __('Christian') }}</option>
                        <option value="Muslim" {{ old('religion', $student->religion) == 'Muslim' ? 'selected' : '' }}>{{ __('Muslim') }}</option>
                        <option value="Other" {{ old('religion', $student->religion) == 'Other' ? 'selected' : '' }}>{{ __('Other') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Phone') }}</label>
                    <input type="text" name="phone" value="{{ old('phone', $student->phone) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Email') }}</label>
                    <input type="email" name="email" value="{{ old('email', $student->email) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Home Address') }}</label>
                    <input type="text" name="home_address" value="{{ old('home_address', $student->home_address) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Status') }} <span class="text-red-500">*</span></label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" required>
                        @foreach(['active','graduated','withdrawn','suspended','expelled'] as $s)
                            <option value="{{ $s }}" {{ old('status', $student->status) == $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Enrollment --}}
        @if($currentSession && $student->currentEnrollment)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-4">
            <h3 class="text-lg font-semibold text-gray-700 mb-4 border-b pb-2">{{ __('Current Enrollment') }}</h3>
            <p class="text-sm text-gray-500 mb-3">{{ __('Session') }}: <strong>{{ $currentSession->name }}</strong></p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Class') }}</label>
                    <select name="class_section_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        @foreach($classSections as $cs)
                            <option value="{{ $cs->id }}" {{ old('class_section_id', $student->currentEnrollment->class_section_id) == $cs->id ? 'selected' : '' }}>{{ $cs->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Residence Type') }}</label>
                    <select name="residence_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        <option value="day" {{ old('residence_type', $student->currentEnrollment->residence_type) == 'day' ? 'selected' : '' }}>{{ __('Day') }}</option>
                        <option value="boarding" {{ old('residence_type', $student->currentEnrollment->residence_type) == 'boarding' ? 'selected' : '' }}>{{ __('Boarding') }}</option>
                        <option value="half_boarding" {{ old('residence_type', $student->currentEnrollment->residence_type) == 'half_boarding' ? 'selected' : '' }}>{{ __('Half Boarding') }}</option>
                    </select>
                </div>
            </div>
        </div>
        @endif

        {{-- Guardian Information --}}
        @php $g = $student->guardian; @endphp
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-4">
            <h3 class="text-lg font-semibold text-gray-700 mb-4 border-b pb-2">{{ __('Parent / Guardian Information') }}</h3>

            <h4 class="text-sm font-semibold text-gray-600 mb-3">{{ __("Father's Details") }}</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Full Name') }}</label>
                    <input type="text" name="father_name" value="{{ old('father_name', $g?->father_name) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Phone') }}</label>
                    <input type="text" name="father_phone" value="{{ old('father_phone', $g?->father_phone) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Email') }}</label>
                    <input type="email" name="father_email" value="{{ old('father_email', $g?->father_email) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Occupation') }}</label>
                    <input type="text" name="father_occupation" value="{{ old('father_occupation', $g?->father_occupation) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Address') }}</label>
                    <input type="text" name="father_address" value="{{ old('father_address', $g?->father_address) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
            </div>

            <h4 class="text-sm font-semibold text-gray-600 mb-3">{{ __("Mother's Details") }}</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Full Name') }}</label>
                    <input type="text" name="mother_name" value="{{ old('mother_name', $g?->mother_name) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Phone') }}</label>
                    <input type="text" name="mother_phone" value="{{ old('mother_phone', $g?->mother_phone) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Email') }}</label>
                    <input type="email" name="mother_email" value="{{ old('mother_email', $g?->mother_email) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Occupation') }}</label>
                    <input type="text" name="mother_occupation" value="{{ old('mother_occupation', $g?->mother_occupation) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Address') }}</label>
                    <input type="text" name="mother_address" value="{{ old('mother_address', $g?->mother_address) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
            </div>

            <h4 class="text-sm font-semibold text-gray-600 mb-3">{{ __('Other Guardian') }}</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Full Name') }}</label>
                    <input type="text" name="guardian_name" value="{{ old('guardian_name', $g?->guardian_name) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Relationship') }}</label>
                    <input type="text" name="guardian_relationship" value="{{ old('guardian_relationship', $g?->guardian_relationship) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Phone') }}</label>
                    <input type="text" name="guardian_phone" value="{{ old('guardian_phone', $g?->guardian_phone) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Email') }}</label>
                    <input type="email" name="guardian_email" value="{{ old('guardian_email', $g?->guardian_email) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
            </div>

            <h4 class="text-sm font-semibold text-gray-600 mb-3">{{ __('Emergency Contact') }} <span class="text-red-500">*</span></h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Contact Name') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name', $g?->emergency_contact_name) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" required>
                    @error('emergency_contact_name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Phone') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $g?->emergency_contact_phone) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" required>
                    @error('emergency_contact_phone') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Relationship') }}</label>
                    <input type="text" name="emergency_contact_relationship" value="{{ old('emergency_contact_relationship', $g?->emergency_contact_relationship) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex items-center justify-end space-x-3">
            <a href="{{ route('admin.students.show', $student) }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">{{ __('Cancel') }}</a>
            <button type="submit" class="bg-[#1e293b] hover:bg-[#334155] text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">
                {{ __('Update Student') }}
            </button>
        </div>
    </form>
</div>
@endsection
