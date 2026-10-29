@php
    $sectionLabels = \App\Models\BudgetLine::sectionLabels();
    $headerOptions = $headers->get($section, collect());
@endphp
<div class="grid grid-cols-2 md:grid-cols-6 gap-3 text-xs" x-data="{ header: {{ $line?->is_header ? 'true' : 'false' }} }">
    <div>
        <label class="block text-gray-600 mb-1">{{ __('Code') }}</label>
        <input type="text" name="code" value="{{ $line?->code }}" required maxlength="20" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 font-mono">
    </div>
    <div class="md:col-span-2">
        <label class="block text-gray-600 mb-1">{{ __('Name') }}</label>
        <input type="text" name="name" value="{{ $line?->name }}" required maxlength="191" class="w-full border border-gray-300 rounded-lg px-2 py-1.5">
    </div>
    <div class="md:col-span-2">
        <label class="block text-gray-600 mb-1">{{ __('Name (French)') }}</label>
        <input type="text" name="name_fr" value="{{ $line?->name_fr }}" maxlength="191" class="w-full border border-gray-300 rounded-lg px-2 py-1.5">
    </div>
    <div>
        <label class="block text-gray-600 mb-1">{{ __('Section') }}</label>
        <select name="section" style="appearance:auto;" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 bg-white">
            @foreach($sectionLabels as $value => $label)
                <option value="{{ $value }}" @selected(($line?->section ?? $section) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="md:col-span-2">
        <label class="inline-flex items-center gap-1.5 text-gray-700 mt-5">
            <input type="checkbox" name="is_header" value="1" x-model="header" @checked($line?->is_header)>
            {{ __('This is a heading') }}
        </label>
        <p class="text-gray-400 mt-0.5">{{ __('A title that gathers other lines and is subtotalled. It holds no money of its own.') }}</p>
    </div>
    <div class="md:col-span-2" x-show="!header">
        <label class="block text-gray-600 mb-1">{{ __('Files under which heading') }}</label>
        <select name="parent_id" style="appearance:auto;" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 bg-white">
            <option value="">{{ __('None — stands on its own') }}</option>
            @foreach($headerOptions as $h)
                @if(! $line || $h->id !== $line->id)
                    <option value="{{ $h->id }}" @selected($line?->parent_id === $h->id)>{{ $h->code }} {{ $h->displayName() }}</option>
                @endif
            @endforeach
        </select>
    </div>
    <div x-show="!header">
        <label class="block text-gray-600 mb-1">{{ __('Tuition for level') }}</label>
        <select name="school_level" style="appearance:auto;" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 bg-white">
            <option value="">{{ __('Not tuition') }}</option>
            @foreach($levels as $value => $label)
                <option value="{{ $value }}" @selected($line?->school_level === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div x-show="!header">
        <label class="block text-gray-600 mb-1">{{ __('Profit centre') }}</label>
        <input type="text" name="profit_centre" value="{{ $line?->profit_centre }}" maxlength="191" placeholder="{{ __('e.g. Canteen') }}" class="w-full border border-gray-300 rounded-lg px-2 py-1.5">
    </div>
    <div class="md:col-span-2" x-show="!header">
        <label class="inline-flex items-center gap-1.5 text-gray-700 mt-5">
            <input type="checkbox" name="is_payroll" value="1" @checked($line?->is_payroll)>
            {{ __('Fed by payroll (salaries paid land here)') }}
        </label>
    </div>
</div>
