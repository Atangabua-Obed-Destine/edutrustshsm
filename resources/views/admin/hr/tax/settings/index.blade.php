@extends('layouts.admin')

@section('title', __('Tax Settings'))
@section('breadcrumb', __('Human Resources > Settings > Tax Settings'))

@section('content')
<div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
    {{-- Create form --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h3 class="text-base font-semibold text-gray-700 mb-4">{{ __('Create Tax Setting') }}</h3>
        <form method="POST" action="{{ route('admin.tax-settings.store') }}" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Tax Title') }} *</label>
                <input type="text" name="tax_title" value="{{ old('tax_title') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Tax Group') }}</label>
                <select name="tax_group_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">{{ __('Standalone (No Group)') }}</option>
                    @foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->title }}</option>@endforeach
                </select>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" name="is_dependent" value="1" id="is-dependent"> {{ __('This is a dependent tax') }}</label>
            <div id="depends-on" class="hidden grid grid-cols-2 gap-2">
                <select name="depends_on_type" style="appearance:auto;-webkit-appearance:menulist;" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="tax_setting">{{ __('Depends on Tax Setting') }}</option>
                    <option value="tax_group">{{ __('Depends on Tax Group') }}</option>
                </select>
                <select name="depends_on_id" style="appearance:auto;-webkit-appearance:menulist;" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    @foreach($dependables as $d)<option value="{{ $d->id }}">{{ $d->tax_title }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Tax Type') }} *</label>
                <select name="tax_type" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                    <option value="1">{{ __('Percentage') }}</option>
                    <option value="2">{{ __('Fixed Amount') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Paid By') }} *</label>
                <select name="paid_by" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                    <option value="employee">{{ __('Employee Only') }}</option>
                    <option value="employer">{{ __('Employer Only') }}</option>
                    <option value="both">{{ __('Shared (Both)') }}</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Min Amount') }} *</label><input type="number" step="0.01" name="min_amount" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Max Amount') }} *</label><input type="number" step="0.01" name="max_amount" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Employee %') }}</label><input type="number" step="0.0001" name="percentage" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Employee Fixed') }}</label><input type="number" step="0.01" name="fixed_amount" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Employer %') }}</label><input type="number" step="0.0001" name="employer_percentage" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Employer Fixed') }}</label><input type="number" step="0.01" name="employer_fixed_amount" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Tax-free Allowance') }}</label><input type="number" step="0.01" name="max_no_taxable_amount" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Bracket Order') }}</label><input type="number" name="bracket_order" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
            </div>
            <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Save') }}</button>
        </form>
    </div>

    {{-- List --}}
    <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <div class="px-5 py-4 border-b border-gray-100"><h3 class="text-base font-semibold text-gray-700">{{ __('Tax Setting List') }}</h3></div>
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Title') }}</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Group') }}</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Min') }}</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Max') }}</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Value') }}</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Paid By') }}</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($settings as $s)
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2 text-sm font-medium text-gray-900">{{ $s->tax_title }} @if($s->is_dependent)<span class="text-xs text-purple-500">[dep]</span>@endif</td>
                    <td class="px-3 py-2 text-sm text-gray-600">{{ $s->taxGroup?->title ?? __('Standalone') }}</td>
                    <td class="px-3 py-2 text-sm text-right">{{ number_format($s->min_amount, 0) }}</td>
                    <td class="px-3 py-2 text-sm text-right">{{ number_format($s->max_amount, 0) }}</td>
                    <td class="px-3 py-2 text-sm text-gray-700">
                        @if($s->tax_type == 1){{ rtrim(rtrim(number_format($s->percentage,4),'0'),'.') }}%@else{{ number_format($s->fixed_amount, 0) }}@endif
                    </td>
                    <td class="px-3 py-2 text-sm text-gray-600 capitalize">{{ $s->paid_by }}</td>
                    <td class="px-3 py-2 text-right space-x-1 whitespace-nowrap">
                        <button onclick="openExemptions({{ $s->id }})" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">{{ __('Exempt') }}</button>
                        <form method="POST" action="{{ route('admin.tax-settings.destroy', $s) }}" class="inline" onsubmit="return confirm('{{ __('Delete?') }}')">@csrf @method('DELETE')<button class="text-red-600 hover:text-red-800 text-xs font-medium">{{ __('Delete') }}</button></form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-3 py-8 text-center text-gray-500">{{ __('No tax settings yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-5 py-3">{{ $settings->links() }}</div>
    </div>
</div>

{{-- Exemptions modal --}}
<div id="exempt-modal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-lg">
        <div class="px-5 py-3 border-b flex items-center justify-between"><h4 class="font-semibold text-gray-700" id="exempt-title">{{ __('Manage Exemptions') }}</h4><button onclick="closeExemptions()" class="text-gray-400 hover:text-gray-600">&times;</button></div>
        <div class="p-5 space-y-3">
            <form method="POST" id="exempt-form" action="">
                @csrf
                <div class="grid grid-cols-2 gap-2">
                    <div class="col-span-2"><label class="block text-xs text-gray-500 mb-1">{{ __('Staff') }} *</label><select name="user_id" id="exempt-staff" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required></select></div>
                    <div><label class="block text-xs text-gray-500 mb-1">{{ __('Custom %') }}</label><input type="number" step="0.0001" name="custom_percentage" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                    <div><label class="block text-xs text-gray-500 mb-1">{{ __('Custom Fixed') }}</label><input type="number" step="0.01" name="custom_fixed_amount" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                    <div class="col-span-2"><label class="block text-xs text-gray-500 mb-1">{{ __('Expires At (blank = permanent)') }}</label><input type="date" name="expires_at" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                </div>
                <p class="text-xs text-gray-400 mt-1">{{ __('Leave custom values blank for a full exemption.') }}</p>
                <button type="submit" class="mt-3 bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm font-medium">+ {{ __('Add Exemption') }}</button>
            </form>
            <div id="exempt-list" class="text-sm text-gray-600 border-t pt-3"></div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.getElementById('is-dependent').addEventListener('change', e => {
        document.getElementById('depends-on').classList.toggle('hidden', !e.target.checked);
    });
    const exemptBase = "{{ url('admin/tax-settings') }}";
    function openExemptions(id) {
        document.getElementById('exempt-form').action = `${exemptBase}/${id}/exemptions`;
        fetch(`${exemptBase}/${id}/exemptions`).then(r => r.json()).then(d => {
            document.getElementById('exempt-title').textContent = '{{ __('Exemptions') }} — ' + d.setting.title;
            document.getElementById('exempt-staff').innerHTML = '<option value="">{{ __('Select staff') }}</option>' + d.staff.map(s => `<option value="${s.id}">${s.label}</option>`).join('');
            document.getElementById('exempt-list').innerHTML = d.exemptions.length
                ? d.exemptions.map(e => `<div class="flex justify-between py-1"><span>${e.staff} ${e.custom ? '('+e.custom+')' : '(full)'} ${e.expires_at ? '· exp '+e.expires_at : ''}</span></div>`).join('')
                : '{{ __('No exemptions') }}';
            document.getElementById('exempt-modal').classList.remove('hidden');
        });
    }
    function closeExemptions() { document.getElementById('exempt-modal').classList.add('hidden'); }
</script>
@endpush
@endsection
