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

    <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Description') }}</label>
        <textarea name="description" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">{{ old('description', $budget?->description) }}</textarea>
    </div>

    <div class="flex items-center justify-end mt-6 space-x-3">
        <a href="{{ route('admin.budget.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('Cancel') }}</a>
        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg text-sm font-medium">{{ __('Save Budget') }}</button>
    </div>
</form>
