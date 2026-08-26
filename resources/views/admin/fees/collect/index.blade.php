@extends('layouts.admin')

@section('title', __('Collect Fees'))
@section('breadcrumb', __('Fees > Collect Fees'))

@section('content')
<div style="max-width: 1300px;">

    {{-- Header --}}
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 700; color: #1e293b;">{{ __('Collect Fees') }}</h2>
            <p style="font-size: 0.825rem; color: #64748b; margin-top: 2px;">{{ __('View and manage student fee collection records.') }}</p>
        </div>
    </div>

    {{-- Filters Card --}}
    <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 24px;">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px;">
            <div style="width: 28px; height: 28px; background: #1e293b; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700;">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            </div>
            <h3 style="font-size: 1rem; font-weight: 600; color: #334155;">{{ __('Filter Students') }}</h3>
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
                {{-- Class Section --}}
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Class Section') }}</label>
                    <select name="section_id" id="sectionSelect"
                            style="width: 100%; padding: 9px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; appearance: auto; -webkit-appearance: menulist; background: #fff;">
                        <option value="">-- {{ __('All Sections') }} --</option>
                        @foreach($sections as $sec)
                            <option value="{{ $sec->id }}" {{ $sectionId == $sec->id ? 'selected' : '' }}>{{ $sec->name }}</option>
                        @endforeach
                    </select>
                </div>

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

                {{-- Payment Status --}}
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Payment Status') }}</label>
                    <select name="status" id="statusSelect"
                            style="width: 100%; padding: 9px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; appearance: auto; -webkit-appearance: menulist; background: #fff;">
                        <option value="">-- {{ __('All Statuses') }} --</option>
                        <option value="unpaid" {{ $status === 'unpaid' ? 'selected' : '' }}>{{ __('Unpaid') }}</option>
                        <option value="partial" {{ $status === 'partial' ? 'selected' : '' }}>{{ __('Partial') }}</option>
                        <option value="paid" {{ $status === 'paid' ? 'selected' : '' }}>{{ __('Fully Paid') }}</option>
                        <option value="overpaid" {{ $status === 'overpaid' ? 'selected' : '' }}>{{ __('Overpaid') }}</option>
                        <option value="waived" {{ $status === 'waived' ? 'selected' : '' }}>{{ __('Waived') }}</option>
                    </select>
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
        {{-- Total Students --}}
        @php
            $uniqueStudents = $studentFees->pluck('enrollment.student.id')->unique()->count();
        @endphp
        <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; padding: 18px;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <p style="font-size: 0.7rem; color: #64748b; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">{{ __('Students') }}</p>
                <span style="width: 32px; height: 32px; background: #f0f9ff; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <svg width="16" height="16" style="color: #0ea5e9;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </span>
            </div>
            <p style="font-size: 1.4rem; font-weight: 700; color: #1e293b; margin-top: 8px;">{{ $uniqueStudents }}</p>
            <p style="font-size: 0.7rem; color: #94a3b8;">{{ __('fee records') }}: {{ $studentFees->count() }}</p>
        </div>

        {{-- Expected --}}
        <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; padding: 18px;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <p style="font-size: 0.7rem; color: #64748b; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">{{ __('Net Expected') }}</p>
                <span style="width: 32px; height: 32px; background: #eff6ff; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <svg width="16" height="16" style="color: #2563eb;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </span>
            </div>
            <p style="font-size: 1.2rem; font-weight: 700; color: #1e293b; font-family: monospace; margin-top: 8px;">{{ number_format($totalExpected) }}</p>
            <p style="font-size: 0.7rem; color: #94a3b8;">XAF</p>
        </div>

        {{-- Collected --}}
        <div style="background: linear-gradient(135deg, #f0fdf4, #ffffff); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #bbf7d0; padding: 18px;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <p style="font-size: 0.7rem; color: #15803d; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">{{ __('Total Paid') }}</p>
                <span style="width: 32px; height: 32px; background: #dcfce7; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <svg width="16" height="16" style="color: #16a34a;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </span>
            </div>
            <p style="font-size: 1.2rem; font-weight: 700; color: #15803d; font-family: monospace; margin-top: 8px;">{{ number_format($totalPaid) }}</p>
            <p style="font-size: 0.7rem; color: #86efac;">XAF</p>
        </div>

        {{-- Outstanding --}}
        <div style="background: linear-gradient(135deg, #fef2f2, #ffffff); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #fecaca; padding: 18px;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <p style="font-size: 0.7rem; color: #dc2626; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">{{ __('Remaining Balance') }}</p>
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
                <h4 style="font-weight: 600; color: #1e293b; font-size: 0.9rem;">{{ __('Fee Collection Records') }}</h4>
                <span style="font-size: 0.75rem; color: #94a3b8; background: #f1f5f9; padding: 2px 10px; border-radius: 9999px;">{{ $studentFees->count() }} {{ __('records') }}</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                {{-- Search --}}
                <div style="position: relative;">
                    <svg style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 14px; height: 14px; color: #94a3b8;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" id="tableSearch" placeholder="{{ __('Search...') }}"
                           style="padding: 7px 12px 7px 32px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.8rem; outline: none; width: 200px;"
                           onfocus="this.style.borderColor='#0ea5e9'; this.style.boxShadow='0 0 0 3px rgba(14,165,233,0.1)'"
                           onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
                </div>
                {{-- Print Selected --}}
                <button type="button" id="printSelectedBtn" disabled
                        style="display: inline-flex; align-items: center; gap: 4px; padding: 7px 14px; font-size: 0.8rem; font-weight: 600; color: #fff; background: #1e293b; border: none; border-radius: 8px; cursor: pointer; opacity: 0.5; transition: all .15s;"
                        onmouseover="if(!this.disabled)this.style.backgroundColor='#334155'" onmouseout="this.style.backgroundColor='#1e293b'"
                        onclick="printSelected()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    {{ __('Print Selected') }}
                </button>
            </div>
        </div>

        @if($studentFees->count())
        <div style="overflow-x: auto;">
            <table style="width: 100%; font-size: 0.83rem; border-collapse: collapse; min-width: 1000px;">
                <thead>
                    <tr style="background: #f1f5f9;">
                        <th style="padding: 10px 14px; text-align: center; width: 40px;">
                            <input type="checkbox" id="selectAll" style="width: 16px; height: 16px; cursor: pointer; accent-color: #0ea5e9;">
                        </th>
                        <th style="padding: 10px 14px; text-align: center; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; width: 40px;">#</th>
                        <th style="padding: 10px 14px; text-align: left; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('Student ID') }}</th>
                        <th style="padding: 10px 14px; text-align: left; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('Student Name') }}</th>
                        <th style="padding: 10px 14px; text-align: left; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('Fee Category') }}</th>
                        <th style="padding: 10px 14px; text-align: left; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('Session') }}</th>
                        <th style="padding: 10px 14px; text-align: right; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('Net Amount') }}</th>
                        <th style="padding: 10px 14px; text-align: right; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('Paid') }}</th>
                        <th style="padding: 10px 14px; text-align: right; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('Remaining Balance') }}</th>
                        <th style="padding: 10px 14px; text-align: center; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('Status') }}</th>
                        <th style="padding: 10px 14px; text-align: center; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; width: 130px;">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($studentFees as $idx => $fee)
                    @php
                        $student = $fee->enrollment->student ?? null;
                        $classSection = $fee->enrollment->classSection ?? null;
                        $balanceVal = (float)$fee->balance;
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
                    @endphp
                    <tr class="fee-row" style="border-top: 1px solid #f1f5f9;"
                        data-search="{{ strtolower(($student->student_id ?? '') . ' ' . ($student->full_name ?? '') . ' ' . ($fee->feeCategory->name ?? '') . ' ' . ($fee->enrollment->academicSession->name ?? '')) }}"
                        onmouseover="this.style.backgroundColor='#fafbfc'" onmouseout="this.style.backgroundColor='transparent'">
                        <td style="padding: 12px 14px; text-align: center;">
                            <input type="checkbox" class="row-check" value="{{ $fee->enrollment->student_id ?? '' }}" style="width: 16px; height: 16px; cursor: pointer; accent-color: #0ea5e9;">
                        </td>
                        <td style="padding: 12px 14px; text-align: center; color: #94a3b8; font-size: 0.78rem;">{{ $idx + 1 }}</td>
                        <td style="padding: 12px 14px;">
                            <span style="font-weight: 600; color: #1e293b; font-size: 0.83rem;">{{ $student->student_id ?? '—' }}</span>
                        </td>
                        <td style="padding: 12px 14px;">
                            <div>
                                <span style="font-weight: 600; color: #1e293b; font-size: 0.83rem;">{{ $student->full_name ?? '—' }}</span>
                                <div style="font-size: 0.72rem; color: #94a3b8; margin-top: 1px;">{{ $classSection->name ?? '' }}</div>
                            </div>
                        </td>
                        <td style="padding: 12px 14px;">
                            <span style="font-weight: 500; color: #475569; font-size: 0.83rem;">{{ $fee->feeCategory->name ?? '—' }}</span>
                            @if($fee->paymentPlan && $fee->paymentPlan->status === 'active')
                                @php
                                    $pp = $fee->paymentPlan;
                                    $ppPaidCount = $pp->installments->where('status', 'paid')->count();
                                @endphp
                                <a href="{{ route('admin.payment-plans.show', $pp) }}" title="{{ __('Payment Plan') }}: {{ $ppPaidCount }}/{{ $pp->number_of_installments }} instalments paid ({{ $pp->progress }}%)"
                                   style="display: inline-flex; align-items: center; gap: 3px; margin-top: 3px; padding: 2px 8px; border-radius: 9999px; font-size: 0.68rem; font-weight: 600; background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; text-decoration: none; white-space: nowrap;"
                                   onmouseover="this.style.background='#dbeafe'" onmouseout="this.style.background='#eff6ff'">
                                    <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                    {{ __('Plan') }} {{ $ppPaidCount }}/{{ $pp->number_of_installments }}
                                </a>
                            @endif
                        </td>
                        <td style="padding: 12px 14px;">
                            <span style="font-weight: 500; color: #475569; font-size: 0.8rem;">{{ $fee->enrollment->academicSession->name ?? '—' }}</span>
                        </td>
                        <td style="padding: 12px 14px; text-align: right; font-family: monospace; font-weight: 500; color: #1e293b;">
                            {{ number_format($fee->net_amount) }} <span style="font-size: 0.7rem; color: #94a3b8;">XAF</span>
                        </td>
                        <td style="padding: 12px 14px; text-align: right; font-family: monospace; font-weight: 600; color: #16a34a;">
                            {{ number_format($fee->paid_amount) }} <span style="font-size: 0.7rem; color: #94a3b8;">XAF</span>
                        </td>
                        <td style="padding: 12px 14px; text-align: right; font-family: monospace; font-weight: 600; color: {{ $balanceVal > 0 ? '#dc2626' : ($balanceVal < 0 ? '#0369a1' : '#16a34a') }};">
                            {{ number_format($fee->balance) }} <span style="font-size: 0.7rem; color: #94a3b8;">XAF</span>
                        </td>
                        <td style="padding: 12px 14px; text-align: center;">
                            <span style="display: inline-block; padding: 3px 12px; font-size: 0.72rem; font-weight: 600; border-radius: 9999px; background: {{ $sc['bg'] }}; color: {{ $sc['text'] }}; border: 1px solid {{ $sc['border'] }};">
                                {{ $statusLabel }}
                            </span>
                        </td>
                        <td style="padding: 12px 14px; text-align: center;">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                                @if($balanceVal > 0 && $student)
                                @php
                                    $activePlan = $fee->paymentPlan && $fee->paymentPlan->status === 'active' ? $fee->paymentPlan : null;
                                    $nextInstallment = $activePlan ? $activePlan->installments->whereIn('status', ['pending', 'partial', 'overdue'])->sortBy('installment_number')->first() : null;
                                    $planJson = $activePlan ? json_encode([
                                        'id' => $activePlan->id,
                                        'total' => (float) $activePlan->total_amount,
                                        'installments' => $activePlan->number_of_installments,
                                        'paidCount' => $activePlan->installments->where('status', 'paid')->count(),
                                        'nextNum' => $nextInstallment ? $nextInstallment->installment_number : null,
                                        'nextAmount' => $nextInstallment ? (float) $nextInstallment->amount - (float) $nextInstallment->paid_amount : 0,
                                        'nextDue' => $nextInstallment ? $nextInstallment->due_date->format('d M Y') : null,
                                        'progress' => $activePlan->progress,
                                    ]) : 'null';
                                @endphp
                                <button type="button"
                                   onclick="openPaymentModal({{ $fee->id }}, '{{ addslashes($student->full_name ?? '') }}', '{{ $student->student_id ?? '' }}', '{{ addslashes($classSection->name ?? '') }}', '{{ addslashes($fee->feeCategory->name ?? '') }}', {{ $fee->net_amount }}, {{ $fee->paid_amount }}, {{ $fee->balance }}, {{ $planJson }})"
                                   title="{{ __('Collect Payment') }}"
                                   style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; background: #0ea5e9; color: #fff; border-radius: 8px; border: none; cursor: pointer; transition: all .15s;"
                                   onmouseover="this.style.backgroundColor='#0284c7'" onmouseout="this.style.backgroundColor='#0ea5e9'">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                </button>
                                @endif
                                @php
                                    $latestPayment = $fee->allocations->sortByDesc(fn($a) => $a->payment->id ?? 0)->first()?->payment;
                                @endphp
                                @if($latestPayment)
                                <button type="button"
                                   onclick="printReceipt({{ $latestPayment->id }})"
                                   title="{{ __('Print Receipt') }} ({{ $latestPayment->receipt_number }})"
                                   class="receipt-btn"
                                   style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; background: #16a34a; color: #fff; border-radius: 8px; border: none; cursor: pointer; transition: all .15s;"
                                   onmouseover="this.style.backgroundColor='#15803d'" onmouseout="this.style.backgroundColor='#16a34a'">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                </button>
                                @endif
                                @if($student)
                                <a href="{{ route('admin.student-fees', $student) }}"
                                   title="{{ __('View Fees') }}"
                                   style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; background: #f1f5f9; color: #475569; border-radius: 8px; text-decoration: none; border: 1px solid #e2e8f0; transition: all .15s;"
                                   onmouseover="this.style.backgroundColor='#e2e8f0'" onmouseout="this.style.backgroundColor='#f1f5f9'">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach

                    {{-- Totals Footer --}}
                    <tr style="border-top: 2px solid #e2e8f0; background: #f8fafc;">
                        <td colspan="6" style="padding: 14px; font-weight: 700; color: #334155; font-size: 0.85rem; text-align: right;">
                            {{ __('Total') }} ({{ $studentFees->count() }} {{ __('records') }})
                        </td>
                        <td style="padding: 14px; text-align: right; font-family: monospace; font-weight: 700; color: #1e293b; font-size: 0.9rem;">
                            {{ number_format($totalExpected) }}
                        </td>
                        <td style="padding: 14px; text-align: right; font-family: monospace; font-weight: 700; color: #16a34a; font-size: 0.9rem;">
                            {{ number_format($totalPaid) }}
                        </td>
                        <td style="padding: 14px; text-align: right; font-family: monospace; font-weight: 700; color: #dc2626; font-size: 0.9rem;">
                            {{ number_format($totalBalance) }}
                        </td>
                        <td colspan="2"></td>
                    </tr>
                </tbody>
            </table>
        </div>
        @else
        <div style="padding: 60px 20px; text-align: center;">
            <svg style="display: inline-block; width: 48px; height: 48px; color: #cbd5e1; margin-bottom: 10px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p style="font-weight: 600; color: #94a3b8;">{{ __('No fee records found for the selected filters.') }}</p>
            <p style="font-size: 0.8rem; color: #cbd5e1; margin-top: 4px;">{{ __('Try adjusting your filter criteria.') }}</p>
        </div>
        @endif
    </div>

    @elseif(!$loaded)
    {{-- Initial State --}}
    <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; padding: 60px 20px; text-align: center;">
        <svg style="display: inline-block; width: 56px; height: 56px; color: #cbd5e1; margin-bottom: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
        <p style="font-weight: 600; color: #94a3b8; font-size: 1rem;">{{ __('Select filters to load fee records') }}</p>
        <p style="font-size: 0.82rem; color: #cbd5e1; margin-top: 6px;">{{ __('Click "Load" to view the fee collection data.') }}</p>
    </div>
    @endif
</div>

{{-- Payment Modal --}}
<div id="paymentModal" style="display: none; position: fixed; inset: 0; z-index: 9999; overflow-y: auto;">
    {{-- Backdrop --}}
    <div onclick="closePaymentModal()" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px);"></div>

    {{-- Modal Content --}}
    <div style="position: relative; z-index: 10; max-width: 560px; margin: 40px auto; background: #fff; border-radius: 16px; box-shadow: 0 25px 50px rgba(0,0,0,0.25); overflow: hidden;">

        {{-- Modal Header --}}
        <div style="background: linear-gradient(135deg, #0ea5e9, #0284c7); padding: 20px 24px; color: #fff;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 36px; height: 36px; background: rgba(255,255,255,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <div>
                        <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0;">{{ __('Collect Payment') }}</h3>
                        <p id="modalStudentName" style="font-size: 0.78rem; opacity: 0.85; margin: 2px 0 0 0;"></p>
                    </div>
                </div>
                <button onclick="closePaymentModal()" style="width: 32px; height: 32px; background: rgba(255,255,255,0.15); border: none; border-radius: 8px; cursor: pointer; color: #fff; display: flex; align-items: center; justify-content: center; transition: all .15s;"
                        onmouseover="this.style.backgroundColor='rgba(255,255,255,0.3)'" onmouseout="this.style.backgroundColor='rgba(255,255,255,0.15)'">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        {{-- Fee Summary Strip --}}
        <div style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 14px 24px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                <div>
                    <p style="font-size: 0.68rem; color: #94a3b8; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin: 0;">{{ __('Fee Category') }}</p>
                    <p id="modalFeeCategory" style="font-size: 0.82rem; font-weight: 600; color: #334155; margin: 3px 0 0 0;"></p>
                </div>
                <div style="text-align: center;">
                    <p style="font-size: 0.68rem; color: #94a3b8; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin: 0;">{{ __('Net Amount') }}</p>
                    <p id="modalNetAmount" style="font-size: 0.82rem; font-weight: 600; color: #1e293b; font-family: monospace; margin: 3px 0 0 0;"></p>
                </div>
                <div style="text-align: right;">
                    <p style="font-size: 0.68rem; color: #dc2626; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin: 0;">{{ __('Remaining Balance') }}</p>
                    <p id="modalBalance" style="font-size: 0.92rem; font-weight: 700; color: #dc2626; font-family: monospace; margin: 3px 0 0 0;"></p>
                </div>
            </div>
            <div style="margin-top: 8px; display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 0.72rem; color: #64748b;">{{ __('Student ID') }}:</span>
                <span id="modalStudentId" style="font-size: 0.78rem; font-weight: 600; color: #334155; font-family: monospace;"></span>
                <span style="color: #d1d5db; margin: 0 4px;">|</span>
                <span id="modalClassName" style="font-size: 0.78rem; color: #64748b;"></span>
            </div>
        </div>

        {{-- Payment Plan Info Banner (hidden by default) --}}
        <div id="modalPlanBanner" style="display: none; background: #eff6ff; border-bottom: 1px solid #bfdbfe; padding: 12px 24px;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                <svg width="16" height="16" style="color: #2563eb; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <span style="font-size: 0.82rem; font-weight: 700; color: #1e40af;">{{ __('This fee is on a Payment Plan') }}</span>
            </div>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; font-size: 0.78rem;">
                <div>
                    <span style="color: #64748b;">{{ __('Progress') }}:</span>
                    <span id="modalPlanProgress" style="font-weight: 600; color: #1e40af; margin-left: 2px;"></span>
                </div>
                <div>
                    <span style="color: #64748b;">{{ __('Instalments') }}:</span>
                    <span id="modalPlanInstalments" style="font-weight: 600; color: #1e40af; margin-left: 2px;"></span>
                </div>
                <div>
                    <span style="color: #64748b;">{{ __('Next (#)') }}:</span>
                    <span id="modalPlanNextNum" style="font-weight: 600; color: #1e40af; margin-left: 2px;"></span>
                </div>
                <div>
                    <span style="color: #64748b;">{{ __('Due') }}:</span>
                    <span id="modalPlanNextDue" style="font-weight: 600; color: #1e40af; margin-left: 2px;"></span>
                </div>
            </div>
            <div style="margin-top: 6px; font-size: 0.75rem; color: #475569;">
                <svg width="12" height="12" style="display: inline; vertical-align: -2px; color: #f59e0b;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ __('The suggested amount below is for the next instalment. Payment will be applied to instalments in order.') }}
            </div>
        </div>

        {{-- Form --}}
        <form id="paymentForm" style="padding: 24px;">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <input type="hidden" id="modalFeeId" name="student_fee_id">

            {{-- Amount --}}
            <div style="margin-bottom: 18px;">
                <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">
                    {{ __('Payment Amount') }} <span style="color: #dc2626;">*</span>
                </label>
                <div style="position: relative;">
                    <input type="number" id="modalAmount" name="amount" step="1" min="1" required
                           style="width: 100%; padding: 10px 70px 10px 14px; border: 1.5px solid #d1d5db; border-radius: 10px; font-size: 0.95rem; font-weight: 600; font-family: monospace; outline: none; box-sizing: border-box;"
                           onfocus="this.style.borderColor='#0ea5e9'; this.style.boxShadow='0 0 0 3px rgba(14,165,233,0.1)'"
                           onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
                    <span style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); font-size: 0.8rem; font-weight: 600; color: #94a3b8;">XAF</span>
                </div>
                <p id="amountHint" style="font-size: 0.72rem; color: #94a3b8; margin-top: 4px;"></p>
            </div>

            {{-- Payment Method + Date --}}
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px;">
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">
                        {{ __('Payment Method') }} <span style="color: #dc2626;">*</span>
                    </label>
                    <select id="modalPaymentMethod" name="payment_method" required
                            style="width: 100%; padding: 10px 14px; border: 1.5px solid #d1d5db; border-radius: 10px; font-size: 0.85rem; outline: none; appearance: auto; -webkit-appearance: menulist; background: #fff; box-sizing: border-box;"
                            onfocus="this.style.borderColor='#0ea5e9'; this.style.boxShadow='0 0 0 3px rgba(14,165,233,0.1)'"
                            onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
                        <option value="cash">{{ __('Cash') }}</option>
                        <option value="bank_transfer">{{ __('Bank Transfer') }}</option>
                        <option value="mtn_momo">{{ __('MTN Mobile Money') }}</option>
                        <option value="orange_money">{{ __('Orange Money') }}</option>
                        <option value="edutrustpay">{{ __('EduTrustPay') }}</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">
                        {{ __('Payment Date') }} <span style="color: #dc2626;">*</span>
                    </label>
                    <input type="date" id="modalPaymentDate" name="payment_date" required
                           style="width: 100%; padding: 10px 14px; border: 1.5px solid #d1d5db; border-radius: 10px; font-size: 0.85rem; outline: none; box-sizing: border-box;"
                           onfocus="this.style.borderColor='#0ea5e9'; this.style.boxShadow='0 0 0 3px rgba(14,165,233,0.1)'"
                           onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
                </div>
            </div>

            {{-- Payer Name + Payer Phone --}}
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px;">
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Payer Name') }}</label>
                    <input type="text" id="modalPayerName" name="payer_name" placeholder="{{ __('Parent/Guardian name') }}"
                           style="width: 100%; padding: 10px 14px; border: 1.5px solid #d1d5db; border-radius: 10px; font-size: 0.85rem; outline: none; box-sizing: border-box;"
                           onfocus="this.style.borderColor='#0ea5e9'; this.style.boxShadow='0 0 0 3px rgba(14,165,233,0.1)'"
                           onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
                </div>
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Payer Phone') }}</label>
                    <input type="text" id="modalPayerPhone" name="payer_phone" placeholder="6XXXXXXXX"
                           style="width: 100%; padding: 10px 14px; border: 1.5px solid #d1d5db; border-radius: 10px; font-size: 0.85rem; outline: none; box-sizing: border-box;"
                           onfocus="this.style.borderColor='#0ea5e9'; this.style.boxShadow='0 0 0 3px rgba(14,165,233,0.1)'"
                           onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
                </div>
            </div>

            {{-- Notes --}}
            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Notes') }}</label>
                <textarea id="modalNotes" name="notes" rows="2" placeholder="{{ __('Optional payment notes...') }}"
                          style="width: 100%; padding: 10px 14px; border: 1.5px solid #d1d5db; border-radius: 10px; font-size: 0.85rem; outline: none; resize: vertical; font-family: inherit; box-sizing: border-box;"
                          onfocus="this.style.borderColor='#0ea5e9'; this.style.boxShadow='0 0 0 3px rgba(14,165,233,0.1)'"
                          onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'"></textarea>
            </div>

            {{-- Error Message --}}
            <div id="paymentError" style="display: none; padding: 10px 14px; margin-bottom: 16px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; color: #dc2626; font-size: 0.82rem;"></div>

            {{-- Action Buttons --}}
            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closePaymentModal()"
                        style="padding: 10px 22px; font-size: 0.85rem; font-weight: 600; color: #64748b; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 10px; cursor: pointer; transition: all .15s;"
                        onmouseover="this.style.backgroundColor='#e2e8f0'" onmouseout="this.style.backgroundColor='#f1f5f9'">
                    {{ __('Cancel') }}
                </button>
                <button type="submit" id="paymentSubmitBtn"
                        style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 28px; font-size: 0.85rem; font-weight: 700; color: #fff; background: #16a34a; border: none; border-radius: 10px; cursor: pointer; transition: all .15s;"
                        onmouseover="this.style.backgroundColor='#15803d'" onmouseout="this.style.backgroundColor='#16a34a'">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ __('Record Payment') }}
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Success Toast --}}
<div id="successToast" style="display: none; position: fixed; top: 24px; right: 24px; z-index: 10000; min-width: 340px; background: #fff; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); border: 1px solid #bbf7d0; overflow: hidden;">
    <div style="display: flex; align-items: flex-start; gap: 12px; padding: 16px 20px;">
        <div style="width: 36px; height: 36px; background: #dcfce7; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <svg width="18" height="18" style="color: #16a34a;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
        </div>
        <div style="flex: 1;">
            <p style="font-weight: 700; color: #15803d; font-size: 0.88rem; margin: 0;">{{ __('Payment Recorded!') }}</p>
            <p id="toastReceipt" style="font-size: 0.8rem; color: #334155; margin: 4px 0 0 0;"></p>
        </div>
        <button onclick="document.getElementById('successToast').style.display='none'"
                style="background: none; border: none; color: #94a3b8; cursor: pointer; padding: 0; margin-top: 2px;">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
</div>

@push('scripts')
<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const baseUrl = @json(url('admin/collect-fees'));
    const formSelect = document.getElementById('formSelect');
    const streamSelect = document.getElementById('streamSelect');
    const sectionSelect = document.getElementById('sectionSelect');

    // Cascading: Form → Stream + Sections
    formSelect.addEventListener('change', async function() {
        streamSelect.innerHTML = '<option value="">-- {{ __("All Streams") }} --</option>';
        sectionSelect.innerHTML = '<option value="">-- {{ __("All Sections") }} --</option>';
        if (!this.value) return;

        try {
            const [streamRes, sectionRes] = await Promise.all([
                fetch(`${baseUrl}/streams-by-form/${this.value}`),
                fetch(`${baseUrl}/sections-by-form/${this.value}`)
            ]);
            const streams = await streamRes.json();
            const sections = await sectionRes.json();

            streams.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.name;
                streamSelect.appendChild(opt);
            });

            sections.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.name;
                sectionSelect.appendChild(opt);
            });
        } catch(e) { console.error('Error loading streams/sections:', e); }
    });

    // Select All checkbox
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.row-check').forEach(cb => {
                const row = cb.closest('.fee-row');
                if (row && row.style.display !== 'none') {
                    cb.checked = selectAll.checked;
                }
            });
            updatePrintBtn();
        });
    }

    // Individual checkboxes
    document.querySelectorAll('.row-check').forEach(cb => {
        cb.addEventListener('change', updatePrintBtn);
    });

    function updatePrintBtn() {
        const btn = document.getElementById('printSelectedBtn');
        if (!btn) return;
        const checkedCount = document.querySelectorAll('.row-check:checked').length;
        btn.disabled = checkedCount === 0;
        btn.style.opacity = checkedCount > 0 ? '1' : '0.5';
    }

    // Table search
    const searchInput = document.getElementById('tableSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase().trim();
            document.querySelectorAll('.fee-row').forEach(row => {
                const data = row.getAttribute('data-search') || '';
                row.style.display = data.includes(term) ? '' : 'none';
            });
        });
    }
});

function printSelected() {
    const checked = document.querySelectorAll('.row-check:checked');
    const studentIds = [...new Set([...checked].map(cb => cb.value))];
    if (studentIds.length === 0) return;
    // Open print-friendly view for selected students (can be extended)
    studentIds.forEach(sid => {
        window.open(@json(url('admin/students')) + '/' + sid + '/fees', '_blank');
    });
}

// ====== Payment Modal ======
const storePaymentUrl = @json(route('admin.collect-fees.store-payment'));
const receiptBaseUrl = @json(url('admin/collect-fees/receipt'));
let currentFeeRowId = null;

function openPaymentModal(feeId, studentName, studentId, className, feeCategory, netAmount, paidAmount, balance, planData) {
    currentFeeRowId = feeId;

    document.getElementById('modalFeeId').value = feeId;
    document.getElementById('modalStudentName').textContent = studentName + ' (' + studentId + ')';
    document.getElementById('modalStudentId').textContent = studentId;
    document.getElementById('modalClassName').textContent = className;
    document.getElementById('modalFeeCategory').textContent = feeCategory;
    document.getElementById('modalNetAmount').textContent = Number(netAmount).toLocaleString() + ' XAF';
    document.getElementById('modalBalance').textContent = Number(balance).toLocaleString() + ' XAF';

    // Payment Plan info
    const planBanner = document.getElementById('modalPlanBanner');
    let suggestedAmount = Math.ceil(balance);

    if (planData && planData.nextNum) {
        planBanner.style.display = 'block';
        document.getElementById('modalPlanProgress').textContent = planData.progress + '%';
        document.getElementById('modalPlanInstalments').textContent = planData.paidCount + '/' + planData.installments;
        document.getElementById('modalPlanNextNum').textContent = '#' + planData.nextNum;
        document.getElementById('modalPlanNextDue').textContent = planData.nextDue || '—';
        suggestedAmount = Math.ceil(planData.nextAmount);
    } else {
        planBanner.style.display = 'none';
    }

    const amountInput = document.getElementById('modalAmount');
    amountInput.value = suggestedAmount;
    amountInput.max = Math.ceil(balance);
    document.getElementById('amountHint').textContent = '{{ __("Outstanding balance") }}: ' + Number(balance).toLocaleString() + ' XAF' +
        (planData && planData.nextNum ? ' · {{ __("Instalment") }} #' + planData.nextNum + ': ' + Number(planData.nextAmount).toLocaleString() + ' XAF' : '');

    // Set today's date
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('modalPaymentDate').value = today;

    // Reset form state
    document.getElementById('modalPaymentMethod').value = 'cash';
    document.getElementById('modalPayerName').value = '';
    document.getElementById('modalPayerPhone').value = '';
    document.getElementById('modalNotes').value = '';
    document.getElementById('paymentError').style.display = 'none';
    document.getElementById('paymentSubmitBtn').disabled = false;
    document.getElementById('paymentSubmitBtn').style.opacity = '1';

    document.getElementById('paymentModal').style.display = 'block';
    document.body.style.overflow = 'hidden';

    setTimeout(() => amountInput.select(), 100);
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
    document.body.style.overflow = '';
    currentFeeRowId = null;
}

// Close modal on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('paymentModal').style.display === 'block') {
        closePaymentModal();
    }
});

// Form submission
document.getElementById('paymentForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const submitBtn = document.getElementById('paymentSubmitBtn');
    const errorDiv = document.getElementById('paymentError');
    errorDiv.style.display = 'none';

    // Disable button while processing
    submitBtn.disabled = true;
    submitBtn.style.opacity = '0.6';
    submitBtn.innerHTML = '<svg style="animation: spin 1s linear infinite;" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> {{ __("Processing...") }}';

    const formData = new FormData(this);

    try {
        const response = await fetch(storePaymentUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': formData.get('_token'),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });

        const data = await response.json();

        if (!response.ok) {
            let msg = data.message || '{{ __("An error occurred.") }}';
            if (data.errors) {
                msg = Object.values(data.errors).flat().join('<br>');
            }
            errorDiv.innerHTML = msg;
            errorDiv.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
            submitBtn.innerHTML = '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> {{ __("Record Payment") }}';
            return;
        }

        // Success — update the table row
        updateFeeRow(currentFeeRowId, data);

        closePaymentModal();

        // Show success toast
        const toast = document.getElementById('successToast');
        document.getElementById('toastReceipt').innerHTML = '{{ __("Receipt") }}: ' + data.receipt_number +
            ' <a href="javascript:void(0)" onclick="printReceipt(' + data.payment_id + ')" style="color: #0ea5e9; text-decoration: underline; font-weight: 600;">Print Receipt</a>';
        toast.style.display = 'block';
        setTimeout(() => { toast.style.display = 'none'; }, 8000);

        // Auto-print the receipt immediately
        printReceipt(data.payment_id);

    } catch (err) {
        errorDiv.textContent = '{{ __("Network error. Please try again.") }}';
        errorDiv.style.display = 'block';
        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
        submitBtn.innerHTML = '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> {{ __("Record Payment") }}';
    }
});

function updateFeeRow(feeId, data) {
    // Find the row that contains this fee's button
    const rows = document.querySelectorAll('.fee-row');
    for (const row of rows) {
        const btn = row.querySelector('button[onclick*="openPaymentModal(' + feeId + ',"]');
        if (!btn) continue;

        const cells = row.querySelectorAll('td');
        // cells[7] = Paid, cells[8] = Balance, cells[9] = Status badge, cells[10] = Action

        // Update Paid column
        cells[7].innerHTML = '<span style="font-size: 0.7rem; color: #94a3b8;">XAF</span>';
        cells[7].insertBefore(document.createTextNode(Number(data.paid_amount).toLocaleString() + ' '), cells[7].firstChild);

        // Update Balance column
        const balanceVal = parseFloat(data.balance);
        const balanceColor = balanceVal > 0 ? '#dc2626' : (balanceVal < 0 ? '#0369a1' : '#16a34a');
        cells[8].style.color = balanceColor;
        cells[8].innerHTML = '<span style="font-size: 0.7rem; color: #94a3b8;">XAF</span>';
        cells[8].insertBefore(document.createTextNode(Number(data.balance).toLocaleString() + ' '), cells[8].firstChild);

        // Update Status badge
        const statusMap = {
            'paid': { bg: '#dcfce7', text: '#15803d', border: '#bbf7d0', label: '{{ __("Fully Paid") }}' },
            'partial': { bg: '#fef9c3', text: '#a16207', border: '#fef08a', label: '{{ __("Partial") }}' },
            'unpaid': { bg: '#fee2e2', text: '#dc2626', border: '#fecaca', label: '{{ __("Unpaid") }}' },
            'overpaid': { bg: '#e0f2fe', text: '#0369a1', border: '#bae6fd', label: '{{ __("Overpaid") }}' },
        };
        const st = statusMap[data.status] || statusMap['unpaid'];
        cells[9].innerHTML = '<span style="display: inline-block; padding: 3px 12px; font-size: 0.72rem; font-weight: 600; border-radius: 9999px; background: ' + st.bg + '; color: ' + st.text + '; border: 1px solid ' + st.border + ';">' + st.label + '</span>';

        // Update the payment plan badge in fee category column (cells[5])
        if (data.plan) {
            const catCell = cells[5];
            let badge = catCell.querySelector('a[href*="payment-plans"]');
            if (data.plan.status === 'completed') {
                // Plan completed — change badge to green "Plan Complete"
                if (badge) {
                    badge.style.background = '#dcfce7';
                    badge.style.color = '#15803d';
                    badge.style.borderColor = '#bbf7d0';
                    badge.onmouseover = function() { this.style.background='#bbf7d0'; };
                    badge.onmouseout = function() { this.style.background='#dcfce7'; };
                    badge.innerHTML = '<svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> {{ __("Plan Complete") }}';
                    badge.title = '{{ __("Payment Plan") }}: {{ __("Completed") }}';
                }
            } else if (badge) {
                // Update paid count on existing badge
                const svgHtml = badge.querySelector('svg')?.outerHTML || '';
                badge.innerHTML = svgHtml + ' {{ __("Plan") }} ' + data.plan.paidCount + '/' + data.plan.totalInstallments;
                badge.title = '{{ __("Payment Plan") }}: ' + data.plan.paidCount + '/' + data.plan.totalInstallments + ' {{ __("instalments paid") }} (' + data.plan.progress + '%)';
            }
        }

        // If fully paid, remove the + button
        if (data.balance <= 0) {
            btn.remove();
        }

        // Add or update receipt button
        const actionDiv = cells[10].querySelector('div');
        let existingReceiptBtn = actionDiv.querySelector('.receipt-btn');
        if (existingReceiptBtn) {
            existingReceiptBtn.setAttribute('data-payment-id', data.payment_id);
            existingReceiptBtn.onclick = function() { printReceipt(data.payment_id); };
            existingReceiptBtn.title = '{{ __("Print Receipt") }} (' + data.receipt_number + ')';
        } else {
            const receiptBtn = document.createElement('button');
            receiptBtn.type = 'button';
            receiptBtn.className = 'receipt-btn';
            receiptBtn.setAttribute('data-payment-id', data.payment_id);
            receiptBtn.title = '{{ __("Print Receipt") }} (' + data.receipt_number + ')';
            receiptBtn.style.cssText = 'display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; background: #16a34a; color: #fff; border-radius: 8px; border: none; cursor: pointer; transition: all .15s;';
            receiptBtn.onmouseover = function() { this.style.backgroundColor = '#15803d'; };
            receiptBtn.onmouseout = function() { this.style.backgroundColor = '#16a34a'; };
            receiptBtn.onclick = function() { printReceipt(data.payment_id); };
            receiptBtn.innerHTML = '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';
            // Insert before the view-fees link
            const viewLink = actionDiv.querySelector('a');
            if (viewLink) {
                actionDiv.insertBefore(receiptBtn, viewLink);
            } else {
                actionDiv.appendChild(receiptBtn);
            }
        }

        break;
    }
}

// ====== Silent Print via Hidden Iframe ======
function printReceipt(paymentId) {
    let iframe = document.getElementById('receiptPrintFrame');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.id = 'receiptPrintFrame';
        iframe.name = 'receiptPrintFrame';
        iframe.style.cssText = 'position: fixed; top: -9999px; left: -9999px; width: 0; height: 0; border: none;';
        document.body.appendChild(iframe);
    }
    iframe.src = receiptBaseUrl + '/' + paymentId;
    iframe.onload = function() {
        try {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        } catch(e) {
            // Fallback: open in new tab if cross-origin or print blocked
            window.open(receiptBaseUrl + '/' + paymentId, '_blank');
        }
    };
}
</script>
@endpush
@endsection
