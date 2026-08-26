@php($account = $account ?? null)
<form method="POST" action="{{ $action }}">
    @csrf
    @if(($method ?? 'POST') === 'PUT') @method('PUT') @endif

    <div class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Account Title') }} <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ old('title', $account?->title) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
            @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Account Type') }} <span class="text-red-500">*</span></label>
                <select name="account_type_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
                    <option value="">{{ __('Select') }}</option>
                    @foreach($types as $t)
                        <option value="{{ $t->id }}" {{ (string) old('account_type_id', $account?->account_type_id) === (string) $t->id ? 'selected' : '' }}>{{ $t->title }}</option>
                    @endforeach
                </select>
                @error('account_type_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Account Number') }}</label>
                <input type="text" name="account_number" value="{{ old('account_number', $account?->account_number) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @if(!$account)
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Opening Balance') }} <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0" name="opening_balance" value="{{ old('opening_balance', 0) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
                @error('opening_balance') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            @endif
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Status') }} <span class="text-red-500">*</span></label>
                <select name="status" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
                    <option value="1" {{ (string) old('status', $account?->status ?? 1) === '1' ? 'selected' : '' }}>{{ __('Active') }}</option>
                    <option value="0" {{ (string) old('status', $account?->status ?? 1) === '0' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Description') }}</label>
            <textarea name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">{{ old('description', $account?->description) }}</textarea>
        </div>
    </div>

    <div class="flex items-center justify-end mt-6 space-x-3">
        <a href="{{ route('admin.payment-account.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('Cancel') }}</a>
        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg text-sm font-medium">{{ __('Save') }}</button>
    </div>
</form>
