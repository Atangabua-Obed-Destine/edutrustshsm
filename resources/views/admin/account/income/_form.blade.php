@php($income = $income ?? null)
<form method="POST" action="{{ $action }}" enctype="multipart/form-data">
    @csrf
    @if(($method ?? 'POST') === 'PUT') @method('PUT') @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Category') }} <span class="text-red-500">*</span></label>
            <select name="category_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
                <option value="">{{ __('Select') }}</option>
                @foreach($categories as $c)
                    <option value="{{ $c->id }}" {{ (string) old('category_id', $income?->category_id) === (string) $c->id ? 'selected' : '' }}>{{ $c->title }}</option>
                @endforeach
            </select>
            @error('category_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Title') }} <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ old('title', $income?->title) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
            @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Amount') }} ({{ \App\Models\SchoolSetting::current()->currency ?? 'CFA' }}) <span class="text-red-500">*</span></label>
            <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', $income?->amount) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
            @error('amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Date') }} <span class="text-red-500">*</span></label>
            <input type="date" name="date" value="{{ old('date', optional($income?->date)->toDateString() ?? now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
            @error('date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Invoice ID') }}</label>
            <input type="text" name="invoice_id" value="{{ old('invoice_id', $income?->invoice_id) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Reference') }}</label>
            <input type="text" name="reference" value="{{ old('reference', $income?->reference) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Method') }}</label>
            <select name="payment_method" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">{{ __('Select') }}</option>
                @foreach($methods as $m)
                    <option value="{{ $m }}" {{ old('payment_method', $income?->payment_method) === $m ? 'selected' : '' }}>{{ $m }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Payment Account') }}</label>
            <select name="payment_account_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">{{ __('Select Payment Account (Optional)') }}</option>
                @foreach($accounts as $a)
                    <option value="{{ $a->id }}" {{ (string) old('payment_account_id', $income?->payment_account_id) === (string) $a->id ? 'selected' : '' }}>{{ $a->title }} ({{ number_format($a->current_balance) }})</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">{{ __('If selected, this income is credited to the account.') }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Attachment') }}</label>
            <input type="file" name="attach" class="w-full text-sm text-gray-600">
            @if($income?->attach)
                <a href="{{ Storage::url($income->attach) }}" target="_blank" class="text-xs text-blue-600 hover:underline">{{ __('View current file') }}</a>
            @endif
            @error('attach') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Note') }}</label>
        <textarea name="note" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">{{ old('note', $income?->note) }}</textarea>
    </div>

    <div class="flex items-center justify-end mt-6 space-x-3">
        <a href="{{ route('admin.account.income.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('Cancel') }}</a>
        <button type="submit" class="bg-[#1e293b] hover:bg-[#334155] text-white px-6 py-2 rounded-lg text-sm font-medium">{{ __('Save') }}</button>
    </div>
</form>
