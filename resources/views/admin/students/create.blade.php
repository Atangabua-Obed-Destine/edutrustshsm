@extends('layouts.admin')

@section('title', __('New Student Registration'))
@section('breadcrumb', __('Admissions > New Registration'))

@section('content')
<div class="max-w-5xl">
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-lg font-semibold text-gray-700">{{ __('New Student Registration') }}</h3>
            <p class="text-sm text-gray-500">{{ __('Complete all sections to register a new student.') }}</p>
        </div>
        <a href="{{ route('admin.students.index') }}"
           class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition">
            ← {{ __('Back to Students') }}
        </a>
    </div>

    <!-- Step Indicator -->
    <div class="flex flex-wrap items-center gap-2 mb-8">
        @php
            $steps = [__('Admission & Class'), __('Personal Information'), __('Contact & Address'), __('Family & Guardians'), __('Documents & Upload')];
        @endphp
        @foreach($steps as $i => $label)
        <button type="button" onclick="goToTab({{ $i + 1 }})"
                id="step-btn-{{ $i + 1 }}"
                class="step-btn flex items-center gap-2 px-4 py-2.5 rounded-full text-sm font-medium whitespace-nowrap transition
                       {{ $i === 0 ? 'bg-[#1e293b] text-white' : 'bg-gray-100 text-gray-500' }}">
            <span class="step-num w-6 h-6 flex items-center justify-center rounded-full text-xs font-bold
                         {{ $i === 0 ? 'bg-white text-[#1e293b]' : 'bg-gray-300 text-white' }}">
                {{ $i + 1 }}
            </span>
            <span class="hidden sm:inline">{{ $label }}</span>
        </button>
        @if($i < 4)
        <div class="hidden lg:block flex-1 min-w-[20px] h-px bg-gray-200"></div>
        @endif
        @endforeach
    </div>

    <!-- Validation Errors Summary -->
    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 text-sm">
        <p class="font-medium">{{ __('Please correct the errors below:') }}</p>
        <ul class="mt-1 list-disc list-inside text-xs space-y-0.5">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('admin.students.store') }}" enctype="multipart/form-data" id="registrationForm">
        @csrf

        {{-- ================================================================ --}}
        {{-- TAB 1: ADMISSION & CLASS ASSIGNMENT                              --}}
        {{-- ================================================================ --}}
        <div class="tab-panel" id="tab-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-6">

                <!-- Student Identification -->
                <fieldset>
                    <legend class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">{{ __('Student Identification') }}</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Student ID') }}</label>
                            <input type="text" id="student_id_preview" disabled
                                   value="{{ __('Auto-generated on registration') }}"
                                   class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-500 text-sm">
                            <p class="text-xs text-gray-400 mt-1">{{ __('Format: School Code + Batch Code + Number') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Admission Date') }} <span class="text-red-500">*</span></label>
                            <input type="date" name="admission_date" value="{{ old('admission_date', date('Y-m-d')) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            @error('admission_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </fieldset>

                <hr class="border-gray-100">

                <!-- Class Assignment -->
                <fieldset>
                    <legend class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">{{ __('Class Assignment') }}</legend>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Batch') }} <span class="text-red-500">*</span></label>
                            <select name="batch_id" id="batch_id"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                    style="appearance: auto; -webkit-appearance: menulist;">
                                <option value="">{{ __('— Select Batch —') }}</option>
                                @foreach($batches as $batch)
                                <option value="{{ $batch->id }}" data-shortcode="{{ $batch->shortcode }}"
                                        {{ old('batch_id') == $batch->id ? 'selected' : '' }}>
                                    {{ $batch->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('batch_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Educational System') }} <span class="text-red-500">*</span></label>
                            <select name="education_system" id="education_system"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                    style="appearance: auto; -webkit-appearance: menulist;">
                                <option value="english" {{ old('education_system', 'english') === 'english' ? 'selected' : '' }}>{{ __('English') }}</option>
                                <option value="french" {{ old('education_system') === 'french' ? 'selected' : '' }}>{{ __('French') }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Cycle') }} <span class="text-red-500">*</span></label>
                            <div class="flex gap-6 mt-2">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="cycle" value="first_cycle" id="cycle_first"
                                           {{ old('cycle', 'first_cycle') === 'first_cycle' ? 'checked' : '' }}
                                           class="w-4 h-4 text-blue-600">
                                    <span class="text-sm text-gray-700">{{ __('First Cycle') }} <span class="text-xs text-gray-400">({{ __('Form 1–5') }})</span></span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="cycle" value="second_cycle" id="cycle_second"
                                           {{ old('cycle') === 'second_cycle' ? 'checked' : '' }}
                                           class="w-4 h-4 text-blue-600">
                                    <span class="text-sm text-gray-700">{{ __('Second Cycle') }} <span class="text-xs text-gray-400">({{ __('6th Form') }})</span></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Form') }} <span class="text-red-500">*</span></label>
                            <select name="form_id" id="form_id"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                    style="appearance: auto; -webkit-appearance: menulist;">
                                <option value="">{{ __('— Select Form —') }}</option>
                            </select>
                            @error('form_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div id="stream_wrapper" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Stream') }} <span class="text-red-500">*</span></label>
                            <select name="stream_id" id="stream_id"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                    style="appearance: auto; -webkit-appearance: menulist;">
                                <option value="">{{ __('— Select Stream —') }}</option>
                            </select>
                            @error('stream_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Section') }} <span class="text-red-500">*</span></label>
                            <select name="class_section_id" id="class_section_id"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                    style="appearance: auto; -webkit-appearance: menulist;">
                                <option value="">{{ __('— Select Section —') }}</option>
                            </select>
                            @error('class_section_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Residence Type') }} <span class="text-red-500">*</span></label>
                            <select name="residence_type"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                    style="appearance: auto; -webkit-appearance: menulist;">
                                <option value="day" {{ old('residence_type', 'day') === 'day' ? 'selected' : '' }}>{{ __('Day Student') }}</option>
                                <option value="boarding" {{ old('residence_type') === 'boarding' ? 'selected' : '' }}>{{ __('Boarder') }}</option>
                                <option value="half_boarding" {{ old('residence_type') === 'half_boarding' ? 'selected' : '' }}>{{ __('Half Boarder') }}</option>
                            </select>
                            @error('residence_type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Enrollment Term') }} <span class="text-red-500">*</span></label>
                            <select name="term_id"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                    style="appearance: auto; -webkit-appearance: menulist;">
                                <option value="">{{ __('— Select Term —') }}</option>
                                @foreach($terms as $term)
                                <option value="{{ $term->id }}" {{ old('term_id', $term->is_current ? $term->id : '') == $term->id ? 'selected' : '' }}>
                                    {{ $term->name }}{{ $term->is_current ? ' (' . __('Current') . ')' : '' }}
                                </option>
                                @endforeach
                            </select>
                            @error('term_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </fieldset>

                @if($currentSession)
                <div class="bg-blue-50 border border-blue-100 rounded-lg px-4 py-2 text-xs text-blue-700">
                    <strong>{{ __('Session') }}:</strong> {{ $currentSession->name }} — {{ __('Student will be enrolled in the current academic session.') }}
                </div>
                @else
                <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-2 text-xs text-amber-700">
                    <strong>{{ __('Warning') }}:</strong> {{ __('No active academic session found. The student will be created without enrollment.') }}
                </div>
                @endif
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- TAB 2: PERSONAL INFORMATION                                       --}}
        {{-- ================================================================ --}}
        <div class="tab-panel hidden" id="tab-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-6">

                <fieldset>
                    <legend class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">{{ __('Personal Details') }}</legend>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('First Name') }} <span class="text-red-500">*</span></label>
                            <input type="text" name="first_name" value="{{ old('first_name') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                   placeholder="{{ __('Enter first name') }}">
                            @error('first_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Last Name') }} <span class="text-red-500">*</span></label>
                            <input type="text" name="last_name" value="{{ old('last_name') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                   placeholder="{{ __('Enter last name') }}">
                            @error('last_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Other Names') }}</label>
                            <input type="text" name="other_names" value="{{ old('other_names') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                   placeholder="{{ __('Middle name(s)') }}">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Date of Birth') }} <span class="text-red-500">*</span></label>
                            <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            @error('date_of_birth') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Gender') }} <span class="text-red-500">*</span></label>
                            <select name="gender"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                    style="appearance: auto; -webkit-appearance: menulist;">
                                <option value="">{{ __('— Select —') }}</option>
                                <option value="male" {{ old('gender') === 'male' ? 'selected' : '' }}>{{ __('Male') }}</option>
                                <option value="female" {{ old('gender') === 'female' ? 'selected' : '' }}>{{ __('Female') }}</option>
                            </select>
                            @error('gender') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Blood Group') }}</label>
                            <select name="blood_group"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                    style="appearance: auto; -webkit-appearance: menulist;">
                                <option value="">{{ __('— Select —') }}</option>
                                @foreach(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg)
                                <option value="{{ $bg }}" {{ old('blood_group') === $bg ? 'selected' : '' }}>{{ $bg }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </fieldset>

                <hr class="border-gray-100">

                <fieldset>
                    <legend class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">{{ __('Background') }}</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Nationality') }}</label>
                            <input type="text" name="nationality" value="{{ old('nationality', 'Cameroonian') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Place of Birth') }}</label>
                            <input type="text" name="place_of_birth" value="{{ old('place_of_birth') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                   placeholder="{{ __('e.g., Bamenda') }}">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Region of Origin') }}</label>
                            <select name="region_of_origin"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                    style="appearance: auto; -webkit-appearance: menulist;">
                                <option value="">{{ __('— Select Region —') }}</option>
                                @foreach(['Adamawa', 'Centre', 'East', 'Far North', 'Littoral', 'North', 'North West', 'South', 'South West', 'West'] as $region)
                                <option value="{{ $region }}" {{ old('region_of_origin') === $region ? 'selected' : '' }}>{{ $region }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Religion') }}</label>
                            <select name="religion"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                    style="appearance: auto; -webkit-appearance: menulist;">
                                <option value="">{{ __('— Select —') }}</option>
                                @foreach(['Christianity', 'Islam', 'Traditional', 'Other'] as $r)
                                <option value="{{ $r }}" {{ old('religion') === $r ? 'selected' : '' }}>{{ $r }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </fieldset>
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- TAB 3: CONTACT & ADDRESS                                          --}}
        {{-- ================================================================ --}}
        <div class="tab-panel hidden" id="tab-3">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-6">

                <fieldset>
                    <legend class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">{{ __('Student Contact') }}</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Phone Number') }}</label>
                            <input type="tel" name="phone" value="{{ old('phone') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                   placeholder="{{ __('e.g., 670000000') }}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Email Address') }}</label>
                            <input type="email" name="email" value="{{ old('email') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                   placeholder="student@example.com">
                            @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </fieldset>

                <hr class="border-gray-100">

                <fieldset>
                    <legend class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">{{ __('Residential Address') }}</legend>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Home Address') }}</label>
                        <textarea name="home_address" rows="2"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                  placeholder="{{ __('Street, Quarter, Neighbourhood...') }}">{{ old('home_address') }}</textarea>
                    </div>
                    <div class="max-w-md">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Town / City') }}</label>
                        <input type="text" name="town" value="{{ old('town') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                               placeholder="{{ __('e.g., Bamenda') }}">
                    </div>
                </fieldset>

                <hr class="border-gray-100">

                <fieldset>
                    <legend class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4" id="prev_school_legend">{{ __('Previous School') }}</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" id="prev_school_label">{{ __('Previous School Name') }}</label>
                            <input type="text" name="previous_school" value="{{ old('previous_school') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                   placeholder="{{ __('Name of the school') }}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Previous Class / Level') }}</label>
                            <input type="text" name="previous_class" value="{{ old('previous_class') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                   placeholder="{{ __('e.g., Class 6 or Form 5') }}">
                        </div>
                    </div>
                </fieldset>
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- TAB 4: FAMILY & GUARDIANS                                         --}}
        {{-- ================================================================ --}}
        <div class="tab-panel hidden" id="tab-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-6">

                <!-- Father -->
                <fieldset>
                    <legend class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">{{ __("Father's Information") }}</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Full Name') }}</label>
                            <input type="text" name="father_name" value="{{ old('father_name') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Phone') }}</label>
                            <input type="tel" name="father_phone" value="{{ old('father_phone') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Email') }}</label>
                            <input type="email" name="father_email" value="{{ old('father_email') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            @error('father_email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Occupation') }}</label>
                            <input type="text" name="father_occupation" value="{{ old('father_occupation') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Address') }}</label>
                            <input type="text" name="father_address" value="{{ old('father_address') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>
                </fieldset>

                <hr class="border-gray-100">

                <!-- Mother -->
                <fieldset>
                    <legend class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">{{ __("Mother's Information") }}</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Full Name') }}</label>
                            <input type="text" name="mother_name" value="{{ old('mother_name') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Phone') }}</label>
                            <input type="tel" name="mother_phone" value="{{ old('mother_phone') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Email') }}</label>
                            <input type="email" name="mother_email" value="{{ old('mother_email') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            @error('mother_email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Occupation') }}</label>
                            <input type="text" name="mother_occupation" value="{{ old('mother_occupation') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Address') }}</label>
                            <input type="text" name="mother_address" value="{{ old('mother_address') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>
                </fieldset>

                <hr class="border-gray-100">

                <!-- Other Guardian -->
                <fieldset>
                    <legend class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">{{ __('Other Guardian / Sponsor') }}</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Full Name') }}</label>
                            <input type="text" name="guardian_name" value="{{ old('guardian_name') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Relationship') }}</label>
                            <select name="guardian_relationship"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                    style="appearance: auto; -webkit-appearance: menulist;">
                                <option value="">{{ __('— Select —') }}</option>
                                @foreach(['Uncle', 'Aunt', 'Grandparent', 'Sibling', 'Family Friend', 'Sponsor', 'Other'] as $rel)
                                <option value="{{ $rel }}" {{ old('guardian_relationship') === $rel ? 'selected' : '' }}>{{ $rel }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Phone') }}</label>
                            <input type="tel" name="guardian_phone" value="{{ old('guardian_phone') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Email') }}</label>
                            <input type="email" name="guardian_email" value="{{ old('guardian_email') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            @error('guardian_email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </fieldset>

                <hr class="border-gray-100">

                <!-- Emergency Contact -->
                <fieldset>
                    <legend class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">
                        {{ __('Emergency Contact') }} <span class="text-red-500">*</span>
                    </legend>
                    <div class="bg-amber-50 border border-amber-100 rounded-lg px-4 py-2 text-xs text-amber-700 mb-4">
                        {{ __('At least one emergency contact is required for every student.') }}
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Full Name') }} <span class="text-red-500">*</span></label>
                            <input type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            @error('emergency_contact_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Phone') }} <span class="text-red-500">*</span></label>
                            <input type="tel" name="emergency_contact_phone" value="{{ old('emergency_contact_phone') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            @error('emergency_contact_phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Relationship') }}</label>
                            <select name="emergency_contact_relationship"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                    style="appearance: auto; -webkit-appearance: menulist;">
                                <option value="">{{ __('— Select —') }}</option>
                                @foreach(['Father', 'Mother', 'Uncle', 'Aunt', 'Grandparent', 'Sibling', 'Other'] as $rel)
                                <option value="{{ $rel }}" {{ old('emergency_contact_relationship') === $rel ? 'selected' : '' }}>{{ $rel }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </fieldset>
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- TAB 5: DOCUMENTS & UPLOAD                                         --}}
        {{-- ================================================================ --}}
        <div class="tab-panel hidden" id="tab-5">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-6">

                <!-- Cycle indicator -->
                <div id="doc_cycle_info" class="bg-blue-50 border border-blue-100 rounded-lg px-4 py-2 text-xs text-blue-700">
                    {{ __('Document requirements are based on the cycle selected in Step 1.') }}
                </div>

                <!-- Passport Photo -->
                <fieldset>
                    <legend class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">{{ __('Passport Photograph') }}</legend>
                    <div class="flex items-start gap-6">
                        <div class="shrink-0">
                            <div id="photo_placeholder"
                                 class="w-28 h-36 bg-gray-100 border-2 border-dashed border-gray-300 rounded-lg flex items-center justify-center text-gray-400">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0z M12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <img id="photo_preview" src="#" alt="Preview"
                                 class="w-28 h-36 object-cover rounded-lg border border-gray-200 hidden">
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Upload Photo') }}</label>
                            <input type="file" name="photo_file" id="photo_file" accept="image/*"
                                   class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                            <p class="text-xs text-gray-400 mt-1">{{ __('JPG/PNG, max 2MB. Passport-size recommended.') }}</p>
                            @error('photo_file') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </fieldset>

                <hr class="border-gray-100">

                <!-- Required Documents -->
                <fieldset>
                    <legend class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">{{ __('Required Documents') }}</legend>
                    <div class="space-y-4">
                        <!-- Birth Certificate -->
                        <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-lg">
                            <div class="shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700">{{ __('Birth Certificate') }}</label>
                                <input type="file" name="birth_certificate_file" accept=".pdf,.jpg,.jpeg,.png"
                                       class="mt-1 block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-white file:text-gray-700 hover:file:bg-gray-100">
                                @error('birth_certificate_file') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <!-- Primary School Certificate (First Cycle only) -->
                        <div id="primary_cert_section" class="flex items-center gap-4 p-3 bg-gray-50 rounded-lg">
                            <div class="shrink-0 w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700">{{ __('Primary School Leaving Certificate / Report Card') }}</label>
                                <p class="text-xs text-gray-400">{{ __('Required for First Cycle admission') }}</p>
                                <input type="file" name="primary_certificate_file" accept=".pdf,.jpg,.jpeg,.png"
                                       class="mt-1 block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-white file:text-gray-700 hover:file:bg-gray-100">
                                @error('primary_certificate_file') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <!-- GCE O Level Certificate (Second Cycle only) -->
                        <div id="gce_cert_section" class="hidden flex items-center gap-4 p-3 bg-orange-50 rounded-lg border border-orange-200">
                            <div class="shrink-0 w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-orange-800">
                                    {{ __('GCE Ordinary Level Certificate') }} <span class="text-red-500">*</span>
                                </label>
                                <p class="text-xs text-orange-600">{{ __('Mandatory for Second Cycle (Lower/Upper Sixth) admission') }}</p>
                                <input type="file" name="gce_ol_certificate_file" accept=".pdf,.jpg,.jpeg,.png"
                                       class="mt-1 block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-white file:text-gray-700 hover:file:bg-gray-100">
                                @error('gce_ol_certificate_file') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                </fieldset>

                <hr class="border-gray-100">

                <!-- Additional Documents -->
                <fieldset>
                    <legend class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">{{ __('Additional Documents') }}</legend>
                    <div class="space-y-4">
                        <!-- Transfer Certificate -->
                        <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-lg">
                            <div class="shrink-0 w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700">{{ __('Transfer Certificate') }}</label>
                                <p class="text-xs text-gray-400">{{ __('If student is transferring from another school') }}</p>
                                <input type="file" name="transfer_certificate_file" accept=".pdf,.jpg,.jpeg,.png"
                                       class="mt-1 block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-white file:text-gray-700 hover:file:bg-gray-100">
                                @error('transfer_certificate_file') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <!-- Medical Certificate -->
                        <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-lg">
                            <div class="shrink-0 w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700">{{ __('Medical Certificate') }}</label>
                                <p class="text-xs text-gray-400">{{ __('Optional — health clearance or medical report') }}</p>
                                <input type="file" name="medical_certificate_file" accept=".pdf,.jpg,.jpeg,.png"
                                       class="mt-1 block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-white file:text-gray-700 hover:file:bg-gray-100">
                                @error('medical_certificate_file') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                </fieldset>

                <p class="text-xs text-gray-400">{{ __('Accepted formats: PDF, JPG, PNG. Max file size: 5MB per document.') }}</p>
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- NAVIGATION BUTTONS                                                --}}
        {{-- ================================================================ --}}
        <div class="flex items-center justify-between mt-6">
            <button type="button" id="prevBtn" onclick="prevTab()"
                    class="hidden px-5 py-2.5 border border-gray-300 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                ← {{ __('Previous') }}
            </button>
            <div></div>
            <div class="flex items-center gap-3">
                <button type="button" id="nextBtn" onclick="nextTab()"
                        class="px-6 py-2.5 bg-[#1e293b] text-white rounded-lg text-sm font-medium hover:bg-[#334155] transition">
                    {{ __('Next Step') }} →
                </button>
                <button type="submit" id="submitBtn"
                        class="hidden px-6 py-2.5 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition">
                    ✓ {{ __('Register Student') }}
                </button>
            </div>
        </div>
    </form>
</div>

<script>
// ===================================================================
// TAB MANAGEMENT
// ===================================================================
let currentTab = 1;
const totalTabs = 5;

function goToTab(tab) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
    document.getElementById('tab-' + tab).classList.remove('hidden');

    const baseBtnCls = 'step-btn flex items-center gap-2 px-4 py-2.5 rounded-full text-sm font-medium whitespace-nowrap transition';
    const baseNumCls = 'step-num w-6 h-6 flex items-center justify-center rounded-full text-xs font-bold';

    document.querySelectorAll('.step-btn').forEach((btn, i) => {
        const step = i + 1;
        const num = btn.querySelector('.step-num');
        if (step === tab) {
            btn.className = baseBtnCls + ' bg-[#1e293b] text-white';
            num.className = baseNumCls + ' bg-white text-[#1e293b]';
        } else if (step < tab) {
            btn.className = baseBtnCls + ' bg-emerald-50 text-emerald-700';
            num.className = baseNumCls + ' bg-emerald-500 text-white';
        } else {
            btn.className = baseBtnCls + ' bg-gray-100 text-gray-500';
            num.className = baseNumCls + ' bg-gray-300 text-white';
        }
    });

    currentTab = tab;
    document.getElementById('prevBtn').classList.toggle('hidden', tab === 1);
    document.getElementById('nextBtn').classList.toggle('hidden', tab === totalTabs);
    document.getElementById('submitBtn').classList.toggle('hidden', tab !== totalTabs);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function nextTab() { if (currentTab < totalTabs) goToTab(currentTab + 1); }
function prevTab() { if (currentTab > 1) goToTab(currentTab - 1); }

// ===================================================================
// FORMS DATA (from server)
// ===================================================================
@php
    $formsJson = $forms->map(function ($f) {
        return ['id' => $f->id, 'name' => $f->name, 'level' => $f->level, 'has_streams' => $f->has_streams, 'education_system' => $f->education_system];
    });
@endphp
const formsData = @json($formsJson);
const schoolCode = @json($settings?->school_code ?? 'SCH');
const oldFormId = @json(old('form_id', ''));
const oldStreamId = @json(old('stream_id', ''));
const oldSectionId = @json(old('class_section_id', ''));
const baseUrl = @json(url('admin/students'));

// ===================================================================
// CYCLE CHANGE → Filter forms
// ===================================================================
document.querySelectorAll('input[name="cycle"]').forEach(radio => {
    radio.addEventListener('change', filterForms);
});
document.getElementById('education_system').addEventListener('change', filterForms);

function filterForms() {
    const cycle = document.querySelector('input[name="cycle"]:checked')?.value;
    const eduSystem = document.getElementById('education_system').value;
    const formSelect = document.getElementById('form_id');
    formSelect.innerHTML = '<option value="">' + @json(__('— Select Form —')) + '</option>';

    formsData.filter(f => f.level === cycle && f.education_system === eduSystem).forEach(f => {
        const opt = document.createElement('option');
        opt.value = f.id;
        opt.textContent = f.name;
        if (f.id == oldFormId) opt.selected = true;
        formSelect.appendChild(opt);
    });

    document.getElementById('stream_id').innerHTML = '<option value="">' + @json(__('— Select Stream —')) + '</option>';
    document.getElementById('stream_wrapper').classList.add('hidden');
    document.getElementById('stream_id').required = false;
    document.getElementById('class_section_id').innerHTML = '<option value="">' + @json(__('— Select Section —')) + '</option>';

    updateDocumentSections(cycle);

    const label = document.getElementById('prev_school_label');
    const legend = document.getElementById('prev_school_legend');
    if (cycle === 'second_cycle') {
        if (label) label.textContent = @json(__('Previous Secondary School'));
        if (legend) legend.textContent = @json(__('Previous School (Secondary)'));
    } else {
        if (label) label.textContent = @json(__('Previous School Name'));
        if (legend) legend.textContent = @json(__('Previous School'));
    }

    if (formSelect.value) {
        formSelect.dispatchEvent(new Event('change'));
    }
}

// ===================================================================
// FORM CHANGE → Load streams & sections via AJAX
// ===================================================================
document.getElementById('form_id').addEventListener('change', async function() {
    const formId = this.value;
    const streamSelect = document.getElementById('stream_id');
    const sectionSelect = document.getElementById('class_section_id');

    streamSelect.innerHTML = '<option value="">' + @json(__('— Select Stream —')) + '</option>';
    sectionSelect.innerHTML = '<option value="">' + @json(__('— Select Section —')) + '</option>';
    document.getElementById('stream_wrapper').classList.add('hidden');
    streamSelect.required = false;

    if (!formId) return;

    const form = formsData.find(f => f.id == formId);

    if (form?.has_streams) {
        try {
            const res = await fetch(`${baseUrl}/form-streams/${formId}`);
            const streams = await res.json();
            streams.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.name;
                if (s.id == oldStreamId) opt.selected = true;
                streamSelect.appendChild(opt);
            });
            document.getElementById('stream_wrapper').classList.remove('hidden');
            streamSelect.required = true;
        } catch (e) { console.error('Failed to load streams:', e); }
    }

    try {
        const res = await fetch(`${baseUrl}/form-sections/${formId}`);
        const sections = await res.json();
        sections.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.name;
            if (s.id == oldSectionId) opt.selected = true;
            sectionSelect.appendChild(opt);
        });
    } catch (e) { console.error('Failed to load sections:', e); }
});

// ===================================================================
// BATCH CHANGE → Update Student ID preview
// ===================================================================
document.getElementById('batch_id').addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const shortcode = selected?.dataset?.shortcode || '';
    const preview = document.getElementById('student_id_preview');
    preview.value = shortcode ? `${schoolCode}${shortcode}XXXX` : @json(__('Select a batch first'));
});

// ===================================================================
// DOCUMENT SECTIONS → Show/hide based on cycle
// ===================================================================
function updateDocumentSections(cycle) {
    const primarySection = document.getElementById('primary_cert_section');
    const gceSection = document.getElementById('gce_cert_section');
    const infoDiv = document.getElementById('doc_cycle_info');

    if (cycle === 'second_cycle') {
        primarySection.classList.add('hidden');
        gceSection.classList.remove('hidden');
        infoDiv.innerHTML = '<strong>' + @json(__('Second Cycle')) + ':</strong> ' + @json(__('GCE Ordinary Level certificate is <strong>required</strong> for admission.'));
        infoDiv.className = 'bg-orange-50 border border-orange-200 rounded-lg px-4 py-2 text-xs text-orange-700';
    } else {
        primarySection.classList.remove('hidden');
        gceSection.classList.add('hidden');
        infoDiv.innerHTML = '<strong>' + @json(__('First Cycle')) + ':</strong> ' + @json(__('Primary school leaving certificate or report card is recommended.'));
        infoDiv.className = 'bg-blue-50 border border-blue-100 rounded-lg px-4 py-2 text-xs text-blue-700';
    }
}

// ===================================================================
// PHOTO PREVIEW
// ===================================================================
document.getElementById('photo_file').addEventListener('change', function() {
    const preview = document.getElementById('photo_preview');
    const placeholder = document.getElementById('photo_placeholder');
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.classList.remove('hidden');
            placeholder.classList.add('hidden');
        };
        reader.readAsDataURL(this.files[0]);
    } else {
        preview.classList.add('hidden');
        placeholder.classList.remove('hidden');
    }
});

// ===================================================================
// INITIALIZATION
// ===================================================================
document.addEventListener('DOMContentLoaded', function() {
    filterForms();

    const batchSelect = document.getElementById('batch_id');
    if (batchSelect.value) {
        batchSelect.dispatchEvent(new Event('change'));
    }

    @if($errors->any())
    const tabFields = {
        1: ['batch_id', 'admission_date', 'form_id', 'class_section_id', 'residence_type', 'stream_id'],
        2: ['first_name', 'last_name', 'date_of_birth', 'gender', 'blood_group', 'nationality', 'place_of_birth', 'region_of_origin', 'religion'],
        3: ['phone', 'email', 'home_address', 'town', 'previous_school', 'previous_class'],
        4: ['father_name', 'father_phone', 'father_email', 'father_occupation', 'father_address', 'mother_name', 'mother_phone', 'mother_email', 'mother_occupation', 'mother_address', 'guardian_name', 'guardian_relationship', 'guardian_phone', 'guardian_email', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship'],
        5: ['photo_file', 'birth_certificate_file', 'primary_certificate_file', 'gce_ol_certificate_file', 'transfer_certificate_file', 'medical_certificate_file'],
    };
    const errorKeys = @json($errors->keys());
    for (let tab = 1; tab <= totalTabs; tab++) {
        if (tabFields[tab]?.some(f => errorKeys.includes(f))) {
            goToTab(tab);
            break;
        }
    }
    @endif
});
</script>
@endsection
