@extends('layouts.admin')

@section('title', $template->exists ? __('Edit Recurring Entry') : __('New Recurring Entry'))
@section('breadcrumb', __('Accounting > Recurring Entries'))

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-700 mb-1">{{ $template->exists ? __('Edit Recurring Entry') : __('New Recurring Entry') }}</h3>
    <p class="text-sm text-gray-500 mb-6">{{ __('Each run copies these lines into a new journal entry dated on the run day.') }}</p>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5">
        <ul class="text-sm text-red-700 list-disc list-inside space-y-1">
            @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ $template->exists ? route('admin.recurring-entries.update', $template) : route('admin.recurring-entries.store') }}">
        @csrf
        @if($template->exists) @method('PUT') @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Title') }} <span class="text-red-500">*</span></label>
                <input type="text" name="title" value="{{ old('title', $template->title) }}" required maxlength="150"
                       placeholder="{{ __('e.g. Monthly rent') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Description') }}</label>
                <input type="text" name="description" value="{{ old('description', $template->description) }}" maxlength="255"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Frequency') }} <span class="text-red-500">*</span></label>
                <select name="frequency" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none">
                    @foreach($frequencies as $value => $label)
                    <option value="{{ $value }}" @selected(old('frequency', $template->frequency) === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('First Run') }} <span class="text-red-500">*</span></label>
                <input type="date" name="start_date" value="{{ old('start_date', $template->start_date?->toDateString()) }}" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none">
                @if($template->exists && $template->runs_generated > 0)
                <p class="text-xs text-gray-500 mt-1">{{ __('Already running — changing this does not move the next run (:date).', ['date' => $template->next_run_date->format('d/m/Y')]) }}</p>
                @endif
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Ends On') }}</label>
                <input type="date" name="end_date" value="{{ old('end_date', $template->end_date?->toDateString()) }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none">
                <p class="text-xs text-gray-500 mt-1">{{ __('Leave empty to run indefinitely.') }}</p>
            </div>
            <div class="flex items-start pt-7">
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input type="hidden" name="auto_post" value="0">
                    <input type="checkbox" name="auto_post" value="1" @checked(old('auto_post', $template->auto_post)) class="mt-0.5 rounded">
                    <span>
                        {{ __('Post automatically') }}
                        <span class="block text-xs text-gray-500">{{ __('Otherwise each run is left as a draft for review.') }}</span>
                    </span>
                </label>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-y border-gray-200">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Account') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Description') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase w-40">{{ __('Debit') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase w-40">{{ __('Credit') }}</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody id="lines-body"></tbody>
                <tfoot class="border-t border-gray-200">
                    <tr>
                        <td colspan="2" class="px-3 py-2 text-right text-sm font-semibold">{{ __('Totals') }}</td>
                        <td class="px-3 py-2 text-right text-sm font-bold" id="total-debit">0.00</td>
                        <td class="px-3 py-2 text-right text-sm font-bold" id="total-credit">0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <button type="button" id="add-line" class="mt-3 text-sm text-blue-600 hover:underline">+ {{ __('Add Line') }}</button>

        <div class="flex items-center justify-end mt-6 space-x-3">
            <span id="balance-flag" class="text-sm font-medium text-red-600">{{ __('Not balanced') }}</span>
            <a href="{{ $template->exists ? route('admin.recurring-entries.show', $template) : route('admin.recurring-entries.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('Cancel') }}</a>
            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg text-sm font-medium">{{ __('Save') }}</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    const accounts = @json($accounts->map(fn ($a) => ['id' => $a->id, 'label' => $a->account_code.' — '.$a->account_name]));
    const initial = @json(old('lines', $lines));
    const body = document.getElementById('lines-body');
    let idx = 0;

    function escapeHtml(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function accountOptions(selected) {
        return '<option value="">{{ __('Select account') }}</option>' + accounts.map(a =>
            `<option value="${a.id}" ${String(a.id) === String(selected) ? 'selected' : ''}>${escapeHtml(a.label)}</option>`).join('');
    }
    function addLine(line = {}) {
        const i = idx++;
        const tr = document.createElement('tr');
        tr.className = 'border-b border-gray-100';
        tr.innerHTML = `
            <td class="px-3 py-2"><select name="lines[${i}][account_id]" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">${accountOptions(line.account_id)}</select></td>
            <td class="px-3 py-2"><input type="text" name="lines[${i}][description]" value="${escapeHtml(line.description)}" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm"></td>
            <td class="px-3 py-2"><input type="number" step="0.01" min="0" name="lines[${i}][debit]" value="${escapeHtml(line.debit)}" class="debit w-full px-2 py-1.5 border border-gray-300 rounded text-sm text-right"></td>
            <td class="px-3 py-2"><input type="number" step="0.01" min="0" name="lines[${i}][credit]" value="${escapeHtml(line.credit)}" class="credit w-full px-2 py-1.5 border border-gray-300 rounded text-sm text-right"></td>
            <td class="px-3 py-2 text-center"><button type="button" class="remove text-red-500 hover:text-red-700">&times;</button></td>`;
        body.appendChild(tr);
        tr.querySelectorAll('.debit,.credit').forEach(el => el.addEventListener('input', recalc));
        tr.querySelector('.remove').addEventListener('click', () => { tr.remove(); recalc(); });
    }
    function recalc() {
        let d = 0, c = 0;
        body.querySelectorAll('.debit').forEach(el => d += parseFloat(el.value || 0));
        body.querySelectorAll('.credit').forEach(el => c += parseFloat(el.value || 0));
        document.getElementById('total-debit').textContent = d.toFixed(2);
        document.getElementById('total-credit').textContent = c.toFixed(2);
        const balanced = Math.abs(d - c) < 0.01 && d > 0;
        const flag = document.getElementById('balance-flag');
        flag.textContent = balanced ? '{{ __('Balanced') }}' : '{{ __('Not balanced') }}';
        flag.className = 'text-sm font-medium ' + (balanced ? 'text-green-600' : 'text-red-600');
    }
    document.getElementById('add-line').addEventListener('click', () => addLine());
    const start = Object.values(initial || {});
    (start.length ? start : [{}, {}]).forEach(addLine);
    recalc();
</script>
@endpush
@endsection
