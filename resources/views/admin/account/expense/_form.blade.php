@php($expense = $expense ?? null)
<form method="POST" action="{{ $action }}" enctype="multipart/form-data">
    @csrf
    @if(($method ?? 'POST') === 'PUT') @method('PUT') @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Category') }} <span class="text-red-500">*</span></label>
            <select name="category_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
                <option value="">{{ __('Select') }}</option>
                @foreach($categories as $c)
                    <option value="{{ $c->id }}" {{ (string) old('category_id', $expense?->category_id) === (string) $c->id ? 'selected' : '' }}>{{ $c->title }}</option>
                @endforeach
            </select>
            @error('category_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Title') }} <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ old('title', $expense?->title) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
            @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Amount') }} ({{ \App\Models\SchoolSetting::current()->currency ?? 'CFA' }}) <span class="text-red-500">*</span></label>
            <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', $expense?->amount) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
            @error('amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Date') }} <span class="text-red-500">*</span></label>
            <input type="date" name="date" value="{{ old('date', optional($expense?->date)->toDateString() ?? now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
            @error('date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Invoice ID') }}</label>
            <input type="text" name="invoice_id" value="{{ old('invoice_id', $expense?->invoice_id) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Reference') }}</label>
            <input type="text" name="reference" value="{{ old('reference', $expense?->reference) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Method') }}</label>
            <select name="payment_method" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">{{ __('Select') }}</option>
                @foreach($methods as $m)
                    <option value="{{ $m }}" {{ old('payment_method', $expense?->payment_method) === $m ? 'selected' : '' }}>{{ $m }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Payment Account') }}</label>
            <select name="payment_account_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">{{ __('Select Payment Account (Optional)') }}</option>
                @foreach($accounts as $a)
                    <option value="{{ $a->id }}" {{ (string) old('payment_account_id', $expense?->payment_account_id) === (string) $a->id ? 'selected' : '' }}>{{ $a->title }} ({{ number_format($a->current_balance) }})</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">{{ __('If selected, this expense is debited from the account (needs sufficient funds).') }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Attachment') }}</label>
            <input type="file" name="attach" class="w-full text-sm text-gray-600">
            @if($expense?->attach)
                <a href="{{ private_file_url('expense', $expense, 'attach') }}" target="_blank" class="text-xs text-blue-600 hover:underline">{{ __('View current file') }}</a>
            @endif
            @error('attach') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        @isset($budgets)
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Budget') }}</label>
            <select name="budget_id" id="budget-select" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">{{ __('Select (Optional)') }}</option>
                @foreach($budgets as $b)
                    <option value="{{ $b->id }}" {{ (string) old('budget_id', $expense?->budget_id) === (string) $b->id ? 'selected' : '' }}>{{ $b->title }} ({{ $b->budget_code }})</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">{{ __('Link this expense to an active budget.') }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Budget Allocation') }}</label>
            <select name="budget_allocation_id" id="allocation-select" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">{{ __('Select budget first') }}</option>
                @isset($allocations)
                    @foreach($allocations as $a)
                        <option value="{{ $a->id }}" {{ (string) old('budget_allocation_id', $expense?->budget_allocation_id) === (string) $a->id ? 'selected' : '' }}>{{ $a->title }} — {{ $a->expenseCategory?->title }} ({{ number_format($a->available_amount, 2) }} left)</option>
                    @endforeach
                @endisset
            </select>
        </div>
        @endisset
    </div>

    <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Note') }}</label>
        <textarea name="note" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">{{ old('note', $expense?->note) }}</textarea>
    </div>

    <div class="flex items-center justify-end mt-6 space-x-3">
        <a href="{{ route('admin.account.expense.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('Cancel') }}</a>
        <button type="submit" class="bg-[#1e293b] hover:bg-[#334155] text-white px-6 py-2 rounded-lg text-sm font-medium">{{ __('Save') }}</button>
    </div>
</form>

@isset($budgets)
@push('scripts')
<script>
    const budgetSelect = document.getElementById('budget-select');
    const allocationSelect = document.getElementById('allocation-select');
    const allocBaseUrl = "{{ url('admin/budget') }}";

    budgetSelect?.addEventListener('change', function () {
        allocationSelect.innerHTML = '<option value="">{{ __('Loading…') }}</option>';
        if (!this.value) {
            allocationSelect.innerHTML = '<option value="">{{ __('Select budget first') }}</option>';
            return;
        }
        fetch(`${allocBaseUrl}/${this.value}/allocations-json`)
            .then(r => r.json())
            .then(rows => {
                allocationSelect.innerHTML = '<option value="">{{ __('Select (Optional)') }}</option>';
                rows.forEach(a => {
                    const o = document.createElement('option');
                    o.value = a.id; o.textContent = a.label;
                    allocationSelect.appendChild(o);
                });
            });
    });
</script>
@endpush
@endisset
