@php($budget = $budget ?? null)
<form method="POST" action="{{ $action }}">
    @csrf
    @if(($method ?? 'POST') === 'PUT') @method('PUT') @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Title') }} <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ old('title', $budget?->title) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
            @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Type') }} <span class="text-red-500">*</span></label>
            <select name="type" id="budget-type" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
                @foreach(['annual','departmental','project'] as $t)
                    <option value="{{ $t }}" {{ old('type', $budget?->type) === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                @endforeach
            </select>
            @error('type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Department') }} <span class="text-xs text-gray-400">({{ __('required for departmental') }})</span></label>
            <select name="department_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">{{ __('None') }}</option>
                @foreach($departments as $d)
                    <option value="{{ $d->id }}" {{ (string) old('department_id', $budget?->department_id) === (string) $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                @endforeach
            </select>
            @error('department_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Fiscal Year') }} <span class="text-red-500">*</span></label>
            <input type="text" name="fiscal_year" value="{{ old('fiscal_year', $budget?->fiscal_year ?? now()->year) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
            @error('fiscal_year') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Start Date') }} <span class="text-red-500">*</span></label>
            <input type="date" name="start_date" value="{{ old('start_date', optional($budget?->start_date)->toDateString()) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
            @error('start_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('End Date') }} <span class="text-red-500">*</span></label>
            <input type="date" name="end_date" value="{{ old('end_date', optional($budget?->end_date)->toDateString()) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
            @error('end_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Total Amount') }} <span class="text-red-500">*</span></label>
            <input type="number" step="0.01" min="0" name="total_amount" value="{{ old('total_amount', $budget?->total_amount) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
            @error('total_amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    @if(! $budget && ($previous ?? collect())->isNotEmpty())
    {{-- Only when creating: a budget is nearly always last year plus a judgement. --}}
    <div class="mt-4 bg-gray-50 border border-gray-200 rounded-lg p-4" x-data="{ from: '{{ old('seed_from', '') }}' }">
        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Start from last year') }}</label>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <select name="seed_from" x-model="from" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">{{ __('Start empty') }}</option>
                <option value="actual">{{ __('What was actually spent, line by line') }}</option>
                <option value="budget">{{ __('What was planned, line by line') }}</option>
            </select>
            <select name="seed_budget_id" x-show="from" x-cloak style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                @foreach($previous as $p)
                    <option value="{{ $p->id }}" @selected((string) old('seed_budget_id') === (string) $p->id)>{{ $p->title }} ({{ $p->fiscal_year }})</option>
                @endforeach
            </select>
            <div x-show="from" x-cloak class="flex items-center gap-2">
                <input type="number" step="0.1" name="uplift_percent" value="{{ old('uplift_percent', 0) }}" class="w-24 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <span class="text-sm text-gray-500">{{ __('% uplift') }}</span>
            </div>
        </div>
        <p class="text-xs text-gray-500 mt-2">{{ __('Last year\'s actual spend is the better base: a plan that was never met is a poor start for the next one. The lines land as a draft to review.') }}</p>
        @error('seed_budget_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
    @endif

    <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Description') }}</label>
        <textarea name="description" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">{{ old('description', $budget?->description) }}</textarea>
    </div>

    <div class="flex items-center justify-end mt-6 space-x-3">
        <a href="{{ route('admin.budget.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('Cancel') }}</a>
        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg text-sm font-medium">{{ __('Save Budget') }}</button>
    </div>
</form>
