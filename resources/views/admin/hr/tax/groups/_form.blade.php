@php($group = $group ?? null)
<form method="POST" action="{{ $action }}">
    @csrf
    @if(($method ?? 'POST') === 'PUT') @method('PUT') @endif
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Title') }} <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ old('title', $group?->title) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none" required>
            @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Code') }}</label>
            <input type="text" name="code" value="{{ old('code', $group?->code) }}" placeholder="PIT, CRTV…" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Calculation Type') }} <span class="text-red-500">*</span></label>
            <select name="is_progressive" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none">
                <option value="1" {{ old('is_progressive', $group?->is_progressive) ? 'selected' : '' }}>{{ __('Progressive Tax') }}</option>
                <option value="0" {{ old('is_progressive', $group?->is_progressive) === false || (string)old('is_progressive', $group?->is_progressive) === '0' ? 'selected' : '' }}>{{ __('Flat Rate Tax') }}</option>
            </select>
            <p class="text-xs text-gray-400 mt-1">{{ __('Both use single-bracket step lookup; the bracket value is the tax applied.') }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Display Order') }}</label>
            <input type="number" name="display_order" value="{{ old('display_order', $group?->display_order) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Effective From') }}</label>
            <input type="date" name="effective_from" value="{{ old('effective_from', optional($group?->effective_from)->toDateString()) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Effective To') }}</label>
            <input type="date" name="effective_to" value="{{ old('effective_to', optional($group?->effective_to)->toDateString()) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none">
        </div>
    </div>
    <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Description') }}</label>
        <textarea name="description" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none">{{ old('description', $group?->description) }}</textarea>
    </div>
    <div class="flex items-center justify-end mt-6 space-x-3">
        <a href="{{ route('admin.tax-groups.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('Back') }}</a>
        <button type="submit" class="bg-emerald-500 hover:bg-emerald-600 text-white px-6 py-2 rounded-lg text-sm font-medium">{{ __('Save') }}</button>
    </div>
</form>
