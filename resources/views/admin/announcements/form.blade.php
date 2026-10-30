@extends('layouts.admin')
@section('title', $announcement->exists ? __('Edit Notice') : __('Write a notice'))
@section('breadcrumb', __('Communication > Notice Board'))

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-3xl">
    <h3 class="text-lg font-semibold text-gray-700 mb-6">
        {{ $announcement->exists ? __('Edit Notice') : __('Write a notice') }}
        @if($announcement->reference)<span class="text-sm font-normal text-gray-400">{{ $announcement->reference }}</span>@endif
    </h3>

    <form method="POST"
          action="{{ $announcement->exists ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}"
          enctype="multipart/form-data"
          x-data="{ scope: '{{ old('scope', $announcement->scope) }}' }">
        @csrf
        @if($announcement->exists) @method('PUT') @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Category') }} <span class="text-red-500">*</span></label>
                <select name="category_id" required style="appearance:auto;" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id', $announcement->category_id) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Who should see this') }} <span class="text-red-500">*</span></label>
                <select name="audience" required style="appearance:auto;" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                    <option value="guardians" @selected(old('audience', $announcement->audience) === 'guardians')>{{ __('Parents') }}</option>
                    <option value="staff" @selected(old('audience', $announcement->audience) === 'staff')>{{ __('Staff') }}</option>
                    <option value="both" @selected(old('audience', $announcement->audience) === 'both')>{{ __('Parents and staff') }}</option>
                </select>
                @error('audience') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Title') }} <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ old('title', $announcement->title) }}" required maxlength="200"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Message') }} <span class="text-red-500">*</span></label>
            <textarea name="body" rows="8" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">{{ old('body', $announcement->body) }}</textarea>
            @error('body') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
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
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Class') }}</label>
                <select name="target_form_id" style="appearance:auto;" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                    <option value="">{{ __('— Select Class —') }}</option>
                    @foreach($forms as $form)
                        <option value="{{ $form->id }}" @selected(old('target_form_id', $announcement->target_form_id) == $form->id)>{{ $form->name }}</option>
                    @endforeach
                </select>
                @error('target_form_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div x-show="scope === 'class_specific'" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Class Section') }}</label>
                <select name="target_class_section_id" style="appearance:auto;" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                    <option value="">{{ __('— Select Section —') }}</option>
                    @foreach($sections as $section)
                        <option value="{{ $section->id }}" @selected(old('target_class_section_id', $announcement->target_class_section_id) == $section->id)>{{ $section->name }}</option>
                    @endforeach
                </select>
                @error('target_class_section_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Take it down on') }}</label>
                <input type="date" name="expires_at" value="{{ old('expires_at', $announcement->expires_at?->format('Y-m-d')) }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                <p class="text-xs text-gray-400 mt-1">{{ __('Leave blank to keep it on the board.') }}</p>
                @error('expires_at') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Attachment') }}</label>
                <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                       class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:bg-gray-100">
                @if($announcement->attachment)
                    <p class="text-xs text-gray-500 mt-1">{{ __('A file is attached. Choosing another replaces it.') }}</p>
                @endif
                @error('attachment') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        @unless($announcement->exists)
            <label class="flex items-center gap-2 text-sm text-gray-600 mb-6">
                <input type="checkbox" name="publish" value="1" checked class="rounded border-gray-300 text-blue-600">
                {{ __('Publish it now') }}
            </label>
        @endunless

        <div class="flex items-center justify-end space-x-3">
            <a href="{{ route('admin.announcements.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('Cancel') }}</a>
            <button type="submit" class="bg-[#1e293b] hover:bg-[#334155] text-white px-6 py-2 rounded-lg text-sm font-medium">
                {{ $announcement->exists ? __('Save Notice') : __('Post Notice') }}
            </button>
        </div>
    </form>
</div>
@endsection
