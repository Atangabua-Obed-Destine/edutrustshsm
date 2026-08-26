@extends('layouts.admin')

@section('title', __('Edit Form'))
@section('breadcrumb', __('Academic > Forms > Edit'))

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-700 mb-6">{{ __('Edit Form') }}: {{ $form->name }}</h3>

        <form method="POST" action="{{ route('admin.forms.update', $form) }}">
            @csrf @method('PUT')

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Form Name') }}</label>
                        <input type="text" name="name" value="{{ old('name', $form->name) }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" required>
                        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Short Name') }}</label>
                        <input type="text" name="short_name" value="{{ old('short_name', $form->short_name) }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" required>
                        @error('short_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('School Level') }}</label>
                        @php($levelOptions = \App\Support\LevelContext::availableLabels() + array_intersect_key(\App\Support\LevelContext::labels(), [$form->school_level => true]))
                        <select name="school_level" id="school-level-input" style="appearance: auto; -webkit-appearance: menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" required>
                            @foreach($levelOptions as $value => $label)
                                <option value="{{ $value }}" {{ old('school_level', $form->school_level) == $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('school_level') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Level') }}</label>
                        <select name="level" id="level-input" style="appearance: auto; -webkit-appearance: menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" required></select>
                        @error('level') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Educational System') }}</label>
                    <select name="education_system" style="appearance: auto; -webkit-appearance: menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" required>
                        <option value="english" {{ old('education_system', $form->education_system) == 'english' ? 'selected' : '' }}>{{ __('English') }}</option>
                        <option value="french" {{ old('education_system', $form->education_system) == 'french' ? 'selected' : '' }}>{{ __('French') }}</option>
                    </select>
                    @error('education_system') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Display Order') }}</label>
                    <input type="number" name="display_order" value="{{ old('display_order', $form->display_order) }}" min="1"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" required>
                    @error('display_order') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="has_streams" value="1"
                               {{ old('has_streams', $form->has_streams) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-blue-600 mr-2" id="has-streams-toggle">
                        <span class="text-sm font-medium text-gray-700">{{ __('This form has streams') }}</span>
                    </label>
                </div>

                @php $selectedStreams = old('streams', $form->streams->pluck('id')->toArray()); @endphp
                <div id="streams-section" class="{{ old('has_streams', $form->has_streams) ? '' : 'hidden' }}">
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Select Streams') }}</label>
                    <div class="space-y-2">
                        @foreach($streams as $stream)
                        <label class="flex items-center">
                            <input type="checkbox" name="streams[]" value="{{ $stream->id }}"
                                   {{ in_array($stream->id, $selectedStreams) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-blue-600 mr-2">
                            <span class="text-sm text-gray-700">{{ $stream->name }} ({{ $stream->code }})</span>
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end mt-6 space-x-3">
                <a href="{{ route('admin.forms.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">{{ __('Cancel') }}</a>
                <button type="submit" class="bg-[#1e293b] hover:bg-[#334155] text-white px-6 py-2 rounded-lg text-sm font-medium transition">
                    {{ __('Update Form') }}
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    document.getElementById('has-streams-toggle').addEventListener('change', function() {
        document.getElementById('streams-section').classList.toggle('hidden', !this.checked);
    });

    // Dependent School Level -> Level dropdown
    const LEVEL_OPTIONS = {
        nursery_primary: [
            { value: 'nursery', label: @json(__('Nursery (Nursery 1-3)')) },
            { value: 'primary', label: @json(__('Primary (Class 1-6)')) },
        ],
        secondary: [
            { value: 'first_cycle', label: @json(__('First Cycle (Forms 1-5)')) },
            { value: 'second_cycle', label: @json(__('Second Cycle (Lower/Upper Sixth)')) },
        ],
    };
    const oldLevel = @json(old('level', $form->level));
    const schoolLevelInput = document.getElementById('school-level-input');
    const levelInput = document.getElementById('level-input');

    function populateLevels() {
        const opts = LEVEL_OPTIONS[schoolLevelInput.value] || [];
        levelInput.innerHTML = '';
        opts.forEach(o => {
            const el = document.createElement('option');
            el.value = o.value;
            el.textContent = o.label;
            if (o.value === oldLevel) el.selected = true;
            levelInput.appendChild(el);
        });
    }

    schoolLevelInput.addEventListener('change', populateLevels);
    populateLevels();
</script>
@endpush
@endsection
