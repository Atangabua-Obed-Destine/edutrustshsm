@extends('layouts.admin')

@section('title', __('Assignment History'))
@section('breadcrumb', __('Fees > Assignment History'))

@section('content')
<div style="max-width: 1300px;">

    {{-- Header --}}
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 700; color: #1e293b;">{{ __('Fee Assignment History') }}</h2>
            <p style="font-size: 0.825rem; color: #64748b; margin-top: 2px;">{{ __('View the complete history of fee assignments to students.') }}</p>
        </div>
        <a href="{{ route('admin.quick-assign.create') }}"
           style="display: inline-flex; align-items: center; gap: 6px; padding: 9px 20px; background: #0ea5e9; color: #fff; border-radius: 8px; font-size: 0.85rem; font-weight: 600; text-decoration: none; transition: all .15s;"
           onmouseover="this.style.background='#0284c7'" onmouseout="this.style.background='#0ea5e9'">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('Quick Assign') }}
        </a>
    </div>

    {{-- Success / Error Messages --}}
    @if(session('success'))
    <div id="successAlert" style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 14px 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
        <div style="width: 26px; height: 26px; background: #22c55e; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <svg width="14" height="14" fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
        </div>
        <span style="font-size: 0.85rem; color: #166534; font-weight: 500;">{{ session('success') }}</span>
        <button onclick="document.getElementById('successAlert').style.display='none'" style="margin-left: auto; background: none; border: none; color: #166534; cursor: pointer; font-size: 1.1rem; padding: 0 4px;">&times;</button>
    </div>
    @endif

    @if(session('error'))
    <div id="errorAlert" style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 14px 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
        <div style="width: 26px; height: 26px; background: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <svg width="14" height="14" fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
        </div>
        <span style="font-size: 0.85rem; color: #991b1b; font-weight: 500;">{{ session('error') }}</span>
        <button onclick="document.getElementById('errorAlert').style.display='none'" style="margin-left: auto; background: none; border: none; color: #991b1b; cursor: pointer; font-size: 1.1rem; padding: 0 4px;">&times;</button>
    </div>
    @endif

    {{-- Filters Card --}}
    <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 24px;">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px;">
            <div style="width: 28px; height: 28px; background: #1e293b; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700;">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            </div>
            <h3 style="font-size: 1rem; font-weight: 600; color: #334155;">{{ __('Filter Assignments') }}</h3>
        </div>

        <form method="GET" id="filterForm">
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 16px;">
                {{-- Session --}}
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Academic Session') }}</label>
                    <select name="session_id" id="sessionSelect"
                            style="width: 100%; padding: 9px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; appearance: auto; -webkit-appearance: menulist; background: #fff;">
                        @foreach($sessions as $s)
                            <option value="{{ $s->id }}" {{ $sessionId == $s->id ? 'selected' : '' }}>{{ $s->name }} {{ $s->is_current ? __('(Current)') : '' }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Form --}}
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Form') }}</label>
                    <select name="form_id" id="formSelect"
                            style="width: 100%; padding: 9px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; appearance: auto; -webkit-appearance: menulist; background: #fff;">
                        <option value="">-- {{ __('All Forms') }} --</option>
                        @foreach($forms as $form)
                            <option value="{{ $form->id }}" {{ $formId == $form->id ? 'selected' : '' }}>{{ $form->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Stream --}}
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Stream') }}</label>
                    <select name="stream_id" id="streamSelect"
                            style="width: 100%; padding: 9px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; appearance: auto; -webkit-appearance: menulist; background: #fff;">
                        <option value="">-- {{ __('All Streams') }} --</option>
                        @foreach($streams as $stream)
                            <option value="{{ $stream->id }}" {{ $streamId == $stream->id ? 'selected' : '' }}>{{ $stream->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr) auto; gap: 16px; align-items: end;">
                {{-- Fee Category --}}
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Fee Category') }}</label>
                    <select name="category_id" id="categorySelect"
                            style="width: 100%; padding: 9px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; appearance: auto; -webkit-appearance: menulist; background: #fff;">
                        <option value="">-- {{ __('All Categories') }} --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ $categoryId == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Date From --}}
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Assigned From') }}</label>
                    <input type="date" name="date_from" value="{{ $dateFrom }}"
                           style="width: 100%; padding: 9px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; background: #fff;">
                </div>

                {{-- Date To --}}
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Assigned To') }}</label>
                    <input type="date" name="date_to" value="{{ $dateTo }}"
                           style="width: 100%; padding: 9px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; background: #fff;">
                </div>

                {{-- Load Button --}}
                <div>
                    <button type="submit"
                            style="display: inline-flex; align-items: center; gap: 8px; padding: 9px 24px; background: #0ea5e9; color: #fff; border: none; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; white-space: nowrap; transition: all .15s;"
                            onmouseover="this.style.backgroundColor='#0284c7'" onmouseout="this.style.backgroundColor='#0ea5e9'">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        {{ __('Load') }}
                    </button>
                </div>
            </div>
        </form>
    </div>

    @if($loaded)
    {{-- Summary Cards --}}
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
        {{-- Total Assignments --}}
        <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; padding: 18px;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <p style="font-size: 0.7rem; color: #64748b; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">{{ __('Assignments') }}</p>
                <span style="width: 32px; height: 32px; background: #f0f9ff; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <svg width="16" height="16" style="color: #0ea5e9;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </span>
            </div>
            <p style="font-size: 1.4rem; font-weight: 700; color: #1e293b; margin-top: 8px;">{{ $assignments->count() }}</p>
            <p style="font-size: 0.7rem; color: #94a3b8;">{{ __('fee records') }}</p>
        </div>

        {{-- Total Assigned --}}
        <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; padding: 18px;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <p style="font-size: 0.7rem; color: #64748b; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">{{ __('Total Assigned') }}</p>
                <span style="width: 32px; height: 32px; background: #eff6ff; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <svg width="16" height="16" style="color: #2563eb;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </span>
            </div>
            <p style="font-size: 1.2rem; font-weight: 700; color: #1e293b; font-family: monospace; margin-top: 8px;">{{ number_format($totalAssigned) }}</p>
            <p style="font-size: 0.7rem; color: #94a3b8;">XAF</p>
        </div>

        {{-- Net Amount --}}
        <div style="background: linear-gradient(135deg, #f0fdf4, #ffffff); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #bbf7d0; padding: 18px;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <p style="font-size: 0.7rem; color: #15803d; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">{{ __('Net Amount') }}</p>
                <span style="width: 32px; height: 32px; background: #dcfce7; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <svg width="16" height="16" style="color: #16a34a;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <p style="font-size: 1.2rem; font-weight: 700; color: #15803d; font-family: monospace; margin-top: 8px;">{{ number_format($totalNet) }}</p>
            <p style="font-size: 0.7rem; color: #86efac;">XAF</p>
        </div>

        {{-- Outstanding Balance --}}
        <div style="background: linear-gradient(135deg, #fef2f2, #ffffff); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #fecaca; padding: 18px;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <p style="font-size: 0.7rem; color: #dc2626; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">{{ __('Outstanding Balance') }}</p>
                <span style="width: 32px; height: 32px; background: #fee2e2; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <svg width="16" height="16" style="color: #dc2626;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <p style="font-size: 1.2rem; font-weight: 700; color: #dc2626; font-family: monospace; margin-top: 8px;">{{ number_format($totalBalance) }}</p>
            <p style="font-size: 0.7rem; color: #fca5a5;">XAF</p>
        </div>
    </div>

    {{-- Data Table --}}
    <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; overflow: hidden;">

        {{-- Table Header Bar --}}
        <div style="display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <h4 style="font-weight: 600; color: #1e293b; font-size: 0.9rem;">{{ __('Assignment Records') }}</h4>
                <span style="font-size: 0.75rem; color: #94a3b8; background: #f1f5f9; padding: 2px 10px; border-radius: 9999px;">{{ $assignments->count() }} {{ __('records') }}</span>
            </div>
            <div style="position: relative;">
                <svg style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 14px; height: 14px; color: #94a3b8;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" id="tableSearch" placeholder="{{ __('Search...') }}"
                       style="padding: 7px 12px 7px 32px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.8rem; outline: none; width: 220px;"
                       onfocus="this.style.borderColor='#0ea5e9'; this.style.boxShadow='0 0 0 3px rgba(14,165,233,0.1)'"
                       onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
            </div>
        </div>

        @if($assignments->count())
        <div style="overflow-x: auto;">
            <table style="width: 100%; font-size: 0.83rem; border-collapse: collapse; min-width: 1100px;">
                <thead>
                    <tr style="background: #f1f5f9;">
                        <th style="padding: 10px 14px; text-align: center; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; width: 40px;">#</th>
                        <th style="padding: 10px 14px; text-align: left; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('Student ID') }}</th>
                        <th style="padding: 10px 14px; text-align: left; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('Student Name') }}</th>
                        <th style="padding: 10px 14px; text-align: left; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('Class / Stream') }}</th>
                        <th style="padding: 10px 14px; text-align: left; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('Fee Category') }}</th>
                        <th style="padding: 10px 14px; text-align: right; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('Amount') }}</th>
                        <th style="padding: 10px 14px; text-align: right; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('Net Amount') }}</th>
                        <th style="padding: 10px 14px; text-align: center; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('Status') }}</th>
                        <th style="padding: 10px 14px; text-align: center; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('Due Date') }}</th>
                        <th style="padding: 10px 14px; text-align: center; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('Assigned On') }}</th>
                        <th style="padding: 10px 14px; text-align: center; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; width: 80px;">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($assignments as $idx => $fee)
                    @php
                        $student = $fee->enrollment->student ?? null;
                        $classSection = $fee->enrollment->classSection ?? null;
                        $stream = $fee->enrollment->stream ?? null;
                        $statusColors = [
                            'paid' => ['bg' => '#dcfce7', 'text' => '#15803d', 'border' => '#bbf7d0'],
                            'partial' => ['bg' => '#fef9c3', 'text' => '#a16207', 'border' => '#fef08a'],
                            'unpaid' => ['bg' => '#fee2e2', 'text' => '#dc2626', 'border' => '#fecaca'],
                            'overpaid' => ['bg' => '#e0f2fe', 'text' => '#0369a1', 'border' => '#bae6fd'],
                            'waived' => ['bg' => '#f3f4f6', 'text' => '#6b7280', 'border' => '#d1d5db'],
                        ];
                        $sc = $statusColors[$fee->status] ?? $statusColors['unpaid'];
                        $statusLabel = match($fee->status) {
                            'paid' => __('Fully Paid'),
                            'partial' => __('Partial'),
                            'unpaid' => __('Unpaid'),
                            'overpaid' => __('Overpaid'),
                            'waived' => __('Waived'),
                            default => ucfirst($fee->status),
                        };
                        $hasDueDate = $fee->due_date !== null;
                        $isOverdue = $hasDueDate && $fee->due_date->isPast() && in_array($fee->status, ['unpaid', 'partial']);
                    @endphp
                    <tr class="history-row" style="border-top: 1px solid #f1f5f9;"
                        data-search="{{ strtolower(($student->student_id ?? '') . ' ' . ($student->full_name ?? '') . ' ' . ($fee->feeCategory->name ?? '') . ' ' . ($classSection->name ?? '')) }}"
                        onmouseover="this.style.backgroundColor='#fafbfc'" onmouseout="this.style.backgroundColor='transparent'">
                        <td style="padding: 12px 14px; text-align: center; color: #94a3b8; font-size: 0.78rem;">{{ $idx + 1 }}</td>
                        <td style="padding: 12px 14px;">
                            <span style="font-weight: 600; color: #1e293b; font-size: 0.83rem;">{{ $student->student_id ?? '—' }}</span>
                        </td>
                        <td style="padding: 12px 14px;">
                            <span style="font-weight: 600; color: #1e293b; font-size: 0.83rem;">{{ $student->full_name ?? '—' }}</span>
                        </td>
                        <td style="padding: 12px 14px;">
                            <span style="font-size: 0.83rem; color: #475569;">{{ $classSection->name ?? '—' }}</span>
                            @if($stream)
                            <span style="font-size: 0.72rem; color: #94a3b8; display: block; margin-top: 1px;">{{ $stream->name }}</span>
                            @endif
                        </td>
                        <td style="padding: 12px 14px;">
                            <span style="font-weight: 500; color: #475569; font-size: 0.83rem;">{{ $fee->feeCategory->name ?? '—' }}</span>
                        </td>
                        <td style="padding: 12px 14px; text-align: right; font-family: monospace; font-weight: 500; color: #1e293b;">
                            {{ number_format($fee->original_amount) }}
                        </td>
                        <td style="padding: 12px 14px; text-align: right; font-family: monospace; font-weight: 600; color: #1e293b;">
                            {{ number_format($fee->net_amount) }}
                            @if((float)$fee->discount_amount > 0 || (float)$fee->waiver_amount > 0)
                            <span style="font-size: 0.68rem; color: #f59e0b; display: block;">-{{ number_format($fee->discount_amount + $fee->waiver_amount) }}</span>
                            @endif
                        </td>
                        <td style="padding: 12px 14px; text-align: center;">
                            <span style="display: inline-block; padding: 3px 12px; font-size: 0.72rem; font-weight: 600; border-radius: 9999px; background: {{ $sc['bg'] }}; color: {{ $sc['text'] }}; border: 1px solid {{ $sc['border'] }};">
                                {{ $statusLabel }}
                            </span>
                        </td>
                        <td style="padding: 12px 14px; text-align: center; font-size: 0.8rem; {{ $isOverdue ? 'color: #dc2626; font-weight: 600;' : 'color: #64748b;' }}">
                            @if($hasDueDate)
                                {{ $fee->due_date->format('d M Y') }}
                                @if($isOverdue)
                                <span style="display: block; font-size: 0.68rem; color: #ef4444;">{{ __('Overdue') }}</span>
                                @endif
                            @else
                                <span style="color: #cbd5e1;">—</span>
                            @endif
                        </td>
                        <td style="padding: 12px 14px; text-align: center; font-size: 0.78rem; color: #64748b;">
                            {{ $fee->created_at->format('d M Y') }}
                            <span style="display: block; font-size: 0.68rem; color: #94a3b8;">{{ $fee->created_at->format('h:i A') }}</span>
                        </td>
                        <td style="padding: 12px 14px; text-align: center;">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 5px;">
                                @if($student)
                                <a href="{{ route('admin.student-fees', $student) }}"
                                   title="{{ __('View Fees') }}"
                                   style="display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: #f1f5f9; color: #475569; border-radius: 8px; text-decoration: none; border: 1px solid #e2e8f0; transition: all .15s;"
                                   onmouseover="this.style.backgroundColor='#e2e8f0'" onmouseout="this.style.backgroundColor='#f1f5f9'">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                @endif
                                @if((float)$fee->paid_amount == 0)
                                <form method="POST" action="{{ route('admin.assignment-history.destroy', $fee) }}" style="display: inline;"
                                      onsubmit="return confirm('{{ __('Are you sure you want to delete this fee assignment?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="{{ __('Delete Assignment') }}"
                                            style="display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: #fef2f2; color: #dc2626; border-radius: 8px; border: 1px solid #fecaca; cursor: pointer; transition: all .15s;"
                                            onmouseover="this.style.backgroundColor='#fee2e2'" onmouseout="this.style.backgroundColor='#fef2f2'">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach

                    {{-- Totals Footer --}}
                    <tr style="border-top: 2px solid #e2e8f0; background: #f8fafc;">
                        <td colspan="5" style="padding: 14px; font-weight: 700; color: #334155; font-size: 0.85rem; text-align: right;">
                            {{ __('Total') }} ({{ $assignments->count() }} {{ __('records') }})
                        </td>
                        <td style="padding: 14px; text-align: right; font-family: monospace; font-weight: 700; color: #1e293b; font-size: 0.9rem;">
                            {{ number_format($totalAssigned) }}
                        </td>
                        <td style="padding: 14px; text-align: right; font-family: monospace; font-weight: 700; color: #15803d; font-size: 0.9rem;">
                            {{ number_format($totalNet) }}
                        </td>
                        <td colspan="4"></td>
                    </tr>
                </tbody>
            </table>
        </div>
        @else
        <div style="padding: 60px 20px; text-align: center;">
            <svg style="display: inline-block; width: 48px; height: 48px; color: #cbd5e1; margin-bottom: 10px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            <p style="font-weight: 600; color: #94a3b8;">{{ __('No assignment records found for the selected filters.') }}</p>
            <p style="font-size: 0.8rem; color: #cbd5e1; margin-top: 4px;">{{ __('Try adjusting your filter criteria.') }}</p>
        </div>
        @endif
    </div>

    @elseif(!$loaded)
    {{-- Initial State --}}
    <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; padding: 60px 20px; text-align: center;">
        <svg style="display: inline-block; width: 56px; height: 56px; color: #cbd5e1; margin-bottom: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        <p style="font-weight: 600; color: #94a3b8; font-size: 1rem;">{{ __('Select filters to load assignment history') }}</p>
        <p style="font-size: 0.82rem; color: #cbd5e1; margin-top: 6px;">{{ __('Click "Load" to view the fee assignment records.') }}</p>
    </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const baseUrl = @json(url('admin/assignment-history'));
    const formSelect = document.getElementById('formSelect');
    const streamSelect = document.getElementById('streamSelect');

    // Cascading: Form → Stream
    formSelect.addEventListener('change', async function() {
        streamSelect.innerHTML = '<option value="">-- {{ __("All Streams") }} --</option>';
        if (!this.value) return;

        try {
            const resp = await fetch(baseUrl + '/streams-by-form/' + this.value);
            const streams = await resp.json();
            streams.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.name;
                streamSelect.appendChild(opt);
            });
        } catch (e) {
            console.error('Failed to load streams', e);
        }
    });

    // Table search
    const searchInput = document.getElementById('tableSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const term = this.value.toLowerCase();
            document.querySelectorAll('.history-row').forEach(row => {
                const data = row.getAttribute('data-search') || '';
                row.style.display = data.includes(term) ? '' : 'none';
            });
        });
    }
});
</script>
@endpush

@endsection
