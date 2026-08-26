@php($account = $account ?? null)
@php($sys = $account?->is_system)
<form method="POST" action="{{ $action }}">
    @csrf
    @if(($method ?? 'POST') === 'PUT') @method('PUT') @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Account Code') }} <span class="text-red-500">*</span></label>
            <input type="text" name="account_code" value="{{ old('account_code', $account?->account_code) }}" {{ $sys ? 'readonly' : '' }} class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none {{ $sys ? 'bg-gray-100' : '' }}" required>
            @error('account_code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Class') }} <span class="text-red-500">*</span></label>
            <select name="class_number" {{ $sys ? 'disabled' : '' }} style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none {{ $sys ? 'bg-gray-100' : '' }}" required>
                @for($i = 1; $i <= 8; $i++)<option value="{{ $i }}" {{ (string)old('class_number', $account?->class_number) === (string)$i ? 'selected' : '' }}>{{ __('Class') }} {{ $i }}</option>@endfor
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Account Name (EN)') }} <span class="text-red-500">*</span></label>
            <input type="text" name="account_name" value="{{ old('account_name', $account?->account_name) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none" required>
            @error('account_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Account Name (FR)') }}</label>
            <input type="text" name="account_name_fr" value="{{ old('account_name_fr', $account?->account_name_fr) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Account Type') }} <span class="text-red-500">*</span></label>
            <select name="account_type" {{ $sys ? 'disabled' : '' }} style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none {{ $sys ? 'bg-gray-100' : '' }}" required>
                @foreach(['asset','liability','equity','revenue','expense','other'] as $t)<option value="{{ $t }}" {{ old('account_type', $account?->account_type) === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Category') }} <span class="text-red-500">*</span></label>
            <select name="account_category" {{ $sys ? 'disabled' : '' }} style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none {{ $sys ? 'bg-gray-100' : '' }}" required>
                @foreach(['detail'=>__('Detail (postable)'),'heading'=>__('Heading'),'subtotal'=>__('Subtotal'),'total'=>__('Total')] as $k=>$v)<option value="{{ $k }}" {{ old('account_category', $account?->account_category) === $k ? 'selected' : '' }}>{{ $v }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Normal Balance') }} <span class="text-red-500">*</span></label>
            <select name="normal_balance" {{ $sys ? 'disabled' : '' }} style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none {{ $sys ? 'bg-gray-100' : '' }}" required>
                <option value="debit" {{ old('normal_balance', $account?->normal_balance) === 'debit' ? 'selected' : '' }}>{{ __('Debit') }}</option>
                <option value="credit" {{ old('normal_balance', $account?->normal_balance) === 'credit' ? 'selected' : '' }}>{{ __('Credit') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Parent Account') }}</label>
            <select name="parent_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none">
                <option value="">{{ __('None') }}</option>
                @foreach($parents as $p)<option value="{{ $p->id }}" {{ (string)old('parent_id', $account?->parent_id) === (string)$p->id ? 'selected' : '' }}>{{ $p->account_code }} — {{ $p->account_name }}</option>@endforeach
            </select>
        </div>
    </div>

    @if($sys)<p class="text-xs text-amber-600 mt-3">{{ __('This is a system account — its code, class, type and balance nature are locked.') }}</p>@endif

    <div class="flex items-center justify-end mt-6 space-x-3">
        <a href="{{ route('admin.chart-of-accounts.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('Cancel') }}</a>
        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg text-sm font-medium">{{ __('Save Account') }}</button>
    </div>
</form>
