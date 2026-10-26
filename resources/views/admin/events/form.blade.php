@extends('layouts.admin')
@section('title', $event->exists ? __('Edit Event') : __('Add an event'))
@section('breadcrumb', __('Communication > School Calendar'))

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-3xl">
    <h3 class="text-lg font-semibold text-gray-700 mb-6">{{ $event->exists ? __('Edit Event') : __('Add an event') }}</h3>

    <form method="POST"
          action="{{ $event->exists ? route('admin.events.update', $event) : route('admin.events.store') }}"
          x-data="{ scope: '{{ old('scope', $event->scope) }}' }">
        @csrf
        @if($event->exists) @method('PUT') @endif

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Title') }} <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ old('title', $event->title) }}" required maxlength="200"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Starts') }} <span class="text-red-500">*</span></label>
                <input type="date" name="starts_on" value="{{ old('starts_on', $event->starts_on?->format('Y-m-d')) }}" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                @error('starts_on') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Ends') }} <span class="text-red-500">*</span></label>
                <input type="date" name="ends_on" value="{{ old('ends_on', $event->ends_on?->format('Y-m-d')) }}" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                @error('ends_on') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('From') }}</label>
                <input type="time" name="starts_at" value="{{ old('starts_at', $event->starts_at ? \Illuminate\Support\Str::substr($event->starts_at, 0, 5) : '') }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                <p class="text-xs text-gray-400 mt-1">{{ __('Leave blank for an all-day event.') }}</p>
                @error('starts_at') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('To') }}</label>
                <input type="time" name="ends_at" value="{{ old('ends_at', $event->ends_at ? \Illuminate\Support\Str::substr($event->ends_at, 0, 5) : '') }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                @error('ends_at') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Where') }}</label>
                <input type="text" name="venue" value="{{ old('venue', $event->venue) }}" maxlength="150"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                @error('venue') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Details') }}</label>
            <textarea name="description" rows="5" class="w-full px-4 py-2 border border-gray-300 rounded-lg">{{ old('description', $event->description) }}</textarea>
            @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Category') }}</label>
                <select name="category_id" style="appearance:auto;" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                    <option value="">{{ __('None') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id', $event->category_id) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Who should see this') }} <span class="text-red-500">*</span></label>
                <select name="audience" required style="appearance:auto;" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                    <option value="guardians" @selected(old('audience', $event->audience) === 'guardians')>{{ __('Parents') }}</option>
                    <option value="staff" @selected(old('audience', $event->audience) === 'staff')>{{ __('Staff') }}</option>
                    <option value="both" @selected(old('audience', $event->audience) === 'both')>{{ __('Parents and staff') }}</option>
                </select>
                @error('audience') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('How far it reaches') }} <span class="text-red-500">*</span></label>
                <select name="scope" x-model="scope" required style="appearance:auto;" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                    <option value="all">{{ __('Whole school') }}</option>
                    <option value="form_specific">{{ __('One form') }}</option>
                    <option value="class_specific">{{ __('One class') }}</option>
                </select>
                @error('scope') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div x-show="scope === 'form_specific'" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Form') }}</label>
                <select name="target_form_id" style="appearance:auto;" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                    <option value="">{{ __('— Select Form —') }}</option>
                    @foreach($forms as $form)
                        <option value="{{ $form->id }}" @selected(old('target_form_id', $event->target_form_id) == $form->id)>{{ $form->name }}</option>
                    @endforeach
                </select>
                @error('target_form_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div x-show="scope === 'class_specific'" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Class') }}</label>
                <select name="target_class_section_id" style="appearance:auto;" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                    <option value="">{{ __('— Select Section —') }}</option>
                    @foreach($sections as $section)
                        <option value="{{ $section->id }}" @selected(old('target_class_section_id', $event->target_class_section_id) == $section->id)>{{ $section->name }}</option>
                    @endforeach
                </select>
                @error('target_class_section_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        @unless($event->exists)
            <label class="flex items-center gap-2 text-sm text-gray-600 mb-6">
                <input type="checkbox" name="publish" value="1" checked class="rounded border-gray-300 text-blue-600">
                {{ __('Publish it now') }}
            </label>
        @endunless

        <div class="flex items-center justify-end space-x-3">
            <a href="{{ route('admin.events.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('Cancel') }}</a>
            <button type="submit" class="bg-[#1e293b] hover:bg-[#334155] text-white px-6 py-2 rounded-lg text-sm font-medium">
                {{ $event->exists ? __('Save Event') : __('Add Event') }}
            </button>
        </div>
    </form>
</div>
@endsection
