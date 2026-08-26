@extends('layouts.admin')

@section('title', __('Fee Structures'))
@section('breadcrumb', __('Fees > Fee Structures'))

@section('content')
<div style="max-width: 1100px;">

    {{-- Header --}}
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 700; color: #1e293b;">{{ __('Fee Structures') }}</h2>
            <p style="font-size: 0.825rem; color: #64748b; margin-top: 2px;">{{ __('Manage fee amounts and breakdowns per form and stream.') }}</p>
        </div>
        <a href="{{ route('admin.fee-structures.create') }}"
           style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; background: #1e293b; color: #fff; border-radius: 8px; font-size: 0.85rem; font-weight: 600; text-decoration: none; transition: all .15s;"
           onmouseover="this.style.backgroundColor='#334155'" onmouseout="this.style.backgroundColor='#1e293b'">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('Add Fee Structure') }}
        </a>
    </div>

    @if(session('success'))
    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 0.85rem; display: flex; align-items: center; gap: 8px;">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('success') }}
    </div>
    @endif

    {{-- Filters & Summary --}}
    <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; padding: 20px; margin-bottom: 24px;">
        <div style="display: flex; align-items: end; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            {{-- Form & Stream Filter --}}
            <form method="GET" id="filterForm" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 16px; align-items: end; flex: 1; max-width: 700px;">
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Form') }}</label>
                    <select name="form_id" id="indexFormSelect"
                            style="width: 100%; padding: 8px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; appearance: auto; -webkit-appearance: menulist; background: #fff;">
                        <option value="">-- {{ __('All Forms') }} --</option>
                        @foreach($forms as $form)
                            <option value="{{ $form->id }}" {{ $formId == $form->id ? 'selected' : '' }}>{{ $form->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Stream') }}</label>
                    <select name="stream_id" id="indexStreamSelect"
                            style="width: 100%; padding: 8px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; appearance: auto; -webkit-appearance: menulist; background: #fff;">
                        <option value="">-- {{ __('All Streams') }} --</option>
                        @foreach($streams as $stream)
                            <option value="{{ $stream->id }}" {{ $streamId == $stream->id ? 'selected' : '' }}>{{ $stream->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button type="submit"
                            style="display: inline-flex; align-items: center; gap: 8px; padding: 9px 20px; background: #0ea5e9; color: #fff; border: none; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; white-space: nowrap; transition: all .15s;"
                            onmouseover="this.style.backgroundColor='#0284c7'" onmouseout="this.style.backgroundColor='#0ea5e9'">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        {{ __('Load') }}
                    </button>
                </div>
            </form>

            {{-- Summary Stats --}}
            @php
                $totalCategories = 0;
                $totalAmount = 0;
                $totalGroups = $structures->count();
                foreach ($structures as $items) {
                    $totalCategories += $items->count();
                    $totalAmount += $items->sum('amount');
                }
            @endphp
            <div style="display: flex; gap: 16px;">
                <div style="text-align: center; padding: 6px 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <div style="font-size: 1.1rem; font-weight: 700; color: #1e293b;">{{ $totalGroups }}</div>
                    <div style="font-size: 0.7rem; color: #64748b; font-weight: 500;">{{ __('Groups') }}</div>
                </div>
                <div style="text-align: center; padding: 6px 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <div style="font-size: 1.1rem; font-weight: 700; color: #1e293b;">{{ $totalCategories }}</div>
                    <div style="font-size: 0.7rem; color: #64748b; font-weight: 500;">{{ __('Fee Entries') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Fee Tables grouped by Form + Stream --}}
    @forelse($structures as $group => $items)
    <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 16px;">

        {{-- Group Header (collapsible) --}}
        <div style="display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; cursor: pointer;"
             onclick="var body = this.nextElementSibling; body.style.display = body.style.display === 'none' ? 'block' : 'none'; this.querySelector('.grp-chevron').style.transform = body.style.display === 'none' ? 'rotate(-90deg)' : 'rotate(0deg)';">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 8px; height: 8px; border-radius: 50%; background: #22c55e;"></div>
                <h4 style="font-weight: 600; color: #1e293b; font-size: 0.9rem;">{{ $group }}</h4>
                <span style="font-size: 0.75rem; color: #94a3b8; background: #f1f5f9; padding: 2px 10px; border-radius: 9999px;">{{ $items->count() }} {{ __('categories') }}</span>
            </div>
            <div style="display: flex; align-items: center; gap: 12px;">
                <span style="font-family: monospace; font-weight: 700; color: #15803d; font-size: 0.9rem; background: #f0fdf4; padding: 4px 14px; border-radius: 6px; border: 1px solid #bbf7d0;">
                    {{ number_format($items->sum('amount')) }} XAF
                </span>
                <svg class="grp-chevron" style="width: 18px; height: 18px; color: #94a3b8; transition: transform .2s; transform: rotate(0deg);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </div>

        {{-- Group Body --}}
        <div>
            <table style="width: 100%; font-size: 0.85rem; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f1f5f9;">
                        <th style="padding: 10px 20px; text-align: left; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; width: 30%;">{{ __('Fee Category') }}</th>
                        <th style="padding: 10px 20px; text-align: left; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('Breakdown') }}</th>
                        <th style="padding: 10px 20px; text-align: right; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; width: 150px;">{{ __('Amount (XAF)') }}</th>
                        <th style="padding: 10px 20px; text-align: center; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; width: 110px;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                    <tr style="border-top: 1px solid #f1f5f9;"
                        onmouseover="this.style.backgroundColor='#fafbfc'" onmouseout="this.style.backgroundColor='transparent'">
                        <td style="padding: 14px 20px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 6px; height: 6px; border-radius: 50%; background: {{ $item->feeCategory->is_mandatory ? '#22c55e' : '#94a3b8' }};"></div>
                                <div>
                                    <span style="font-weight: 600; color: #1e293b; font-size: 0.85rem;">{{ $item->feeCategory->name }}</span>
                                    <span style="color: #94a3b8; font-size: 0.72rem; margin-left: 4px;">({{ $item->feeCategory->code }})</span>
                                    <div style="display: flex; gap: 4px; margin-top: 3px;">
                                        @if($item->feeCategory->is_mandatory)
                                            <span style="display: inline-block; padding: 1px 7px; font-size: 0.63rem; font-weight: 600; border-radius: 9999px; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;">{{ __('Mandatory') }}</span>
                                        @endif
                                        @if($item->feeCategory->is_tuition)
                                            <span style="display: inline-block; padding: 1px 7px; font-size: 0.63rem; font-weight: 600; border-radius: 9999px; background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe;">{{ __('Tuition') }}</span>
                                        @endif
                                        @if($item->feeCategory->is_boarding)
                                            <span style="display: inline-block; padding: 1px 7px; font-size: 0.63rem; font-weight: 600; border-radius: 9999px; background: #fefce8; color: #ca8a04; border: 1px solid #fef08a;">{{ __('Boarding') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td style="padding: 14px 20px;">
                            @if($item->breakdowns->count())
                                <div style="display: flex; flex-direction: column; gap: 3px;">
                                    @foreach($item->breakdowns as $bd)
                                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 3px 10px; background: #f8fafc; border-radius: 4px; border: 1px solid #f1f5f9;">
                                            <span style="font-size: 0.78rem; color: #475569;">{{ $bd->name }}</span>
                                            <span style="font-size: 0.78rem; font-family: monospace; color: #334155; font-weight: 500;">{{ number_format($bd->amount) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <span style="font-size: 0.78rem; color: #cbd5e1; font-style: italic;">{{ __('No breakdown') }}</span>
                            @endif
                        </td>
                        <td style="padding: 14px 20px; text-align: right; font-family: monospace; font-weight: 700; color: #1e293b; font-size: 0.9rem;">
                            {{ number_format($item->amount) }}
                        </td>
                        <td style="padding: 14px 20px; text-align: center;">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                                <a href="{{ route('admin.fee-structures.edit', $item) }}"
                                   style="display: inline-flex; align-items: center; gap: 3px; padding: 4px 10px; font-size: 0.75rem; font-weight: 600; color: #d97706; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 6px; text-decoration: none; transition: all .15s;"
                                   onmouseover="this.style.background='#fef3c7'" onmouseout="this.style.background='#fffbeb'">
                                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    {{ __('Edit') }}
                                </a>
                                <form method="POST" action="{{ route('admin.fee-structures.destroy', $item) }}" style="display: inline;" onsubmit="return confirm('{{ __('Delete this fee entry?') }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            style="display: inline-flex; align-items: center; gap: 3px; padding: 4px 10px; font-size: 0.75rem; font-weight: 600; color: #dc2626; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; cursor: pointer; transition: all .15s;"
                                            onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fef2f2'">
                                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        {{ __('Delete') }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach

                    {{-- Group Total Footer --}}
                    <tr style="border-top: 2px solid #e2e8f0; background: #f8fafc;">
                        <td style="padding: 12px 20px; font-weight: 700; color: #334155; font-size: 0.85rem;" colspan="2">{{ __('Total') }}</td>
                        <td style="padding: 12px 20px; text-align: right; font-family: monospace; font-weight: 700; color: #15803d; font-size: 0.95rem;">{{ number_format($items->sum('amount')) }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    @empty
    <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; padding: 60px 20px; text-align: center;">
        <svg style="display: inline-block; width: 48px; height: 48px; color: #cbd5e1; margin-bottom: 10px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p style="font-weight: 600; color: #94a3b8;">{{ __('No fee structures found.') }}</p>
        <p style="font-size: 0.8rem; color: #cbd5e1; margin-top: 4px;">{{ __('Select a Form and Stream above, or click "Add Fee Structure" to get started.') }}</p>
    </div>
    @endforelse
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const baseUrl = @json(url('admin/fee-structures'));
    const formSelect = document.getElementById('indexFormSelect');
    const streamSelect = document.getElementById('indexStreamSelect');

    formSelect.addEventListener('change', async function() {
        streamSelect.innerHTML = '<option value="">-- {{ __("All Streams") }} --</option>';
        if (!this.value) return;
        try {
            const res = await fetch(`${baseUrl}/streams-by-form/${this.value}`);
            const streams = await res.json();
            streams.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.name;
                streamSelect.appendChild(opt);
            });
        } catch(e) { console.error('Error loading streams:', e); }
    });
});
</script>
@endpush
@endsection
