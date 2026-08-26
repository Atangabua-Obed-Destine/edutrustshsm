@extends('layouts.admin')

@section('title', __('Payment Plans'))
@section('breadcrumb', __('Fees > Payment Plans'))

@section('content')
<div style="max-width: 1300px;">

    {{-- Header --}}
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 700; color: #1e293b;">{{ __('Payment Plans') }}</h2>
            <p style="font-size: 0.825rem; color: #64748b; margin-top: 2px;">{{ __('Manage instalment payment plans for student fees.') }}</p>
        </div>
        <a href="{{ route('admin.payment-plans.create') }}"
           style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; background: #1e293b; color: #fff; border-radius: 8px; font-size: 0.85rem; font-weight: 600; text-decoration: none;"
           onmouseover="this.style.background='#334155'" onmouseout="this.style.background='#1e293b'">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('Create Payment Plan') }}
        </a>
    </div>

    @if(session('success'))
        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 0.875rem;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 0.875rem;">{{ session('error') }}</div>
    @endif

    {{-- Filter Card --}}
    <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; padding: 20px; margin-bottom: 24px;">
        <form method="GET" style="display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap;">
            {{-- Status --}}
            <div style="min-width: 180px;">
                <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Status') }}</label>
                <select name="status"
                        style="width: 100%; padding: 9px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; appearance: auto; -webkit-appearance: menulist; background: #fff;">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>{{ __('Completed') }}</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>{{ __('Cancelled') }}</option>
                    <option value="defaulted" {{ request('status') == 'defaulted' ? 'selected' : '' }}>{{ __('Defaulted') }}</option>
                </select>
            </div>

            {{-- Search --}}
            <div style="min-width: 260px; flex: 1;">
                <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Student Name / ID') }}</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search student...') }}"
                       style="width: 100%; padding: 9px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; background: #fff;">
            </div>

            {{-- Filter Button --}}
            <button type="submit"
                    style="padding: 9px 24px; background: #1e293b; color: #fff; border-radius: 8px; border: none; font-size: 0.85rem; font-weight: 600; cursor: pointer;"
                    onmouseover="this.style.background='#334155'" onmouseout="this.style.background='#1e293b'">
                {{ __('Filter') }}
            </button>
            @if(request()->hasAny(['status', 'search']))
                <a href="{{ route('admin.payment-plans.index') }}"
                   style="padding: 9px 18px; background: #f1f5f9; color: #475569; border-radius: 8px; font-size: 0.85rem; font-weight: 500; text-decoration: none;"
                   onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                    {{ __('Clear') }}
                </a>
            @endif
        </form>
    </div>

    {{-- Table Card --}}
    <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; overflow: hidden;">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; white-space: nowrap;">#</th>
                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; white-space: nowrap;">{{ __('Student') }}</th>
                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; white-space: nowrap;">{{ __('Fee') }}</th>
                        <th style="padding: 12px 16px; text-align: right; font-weight: 600; color: #475569; white-space: nowrap;">{{ __('Total Amount') }}</th>
                        <th style="padding: 12px 16px; text-align: center; font-weight: 600; color: #475569; white-space: nowrap;">{{ __('Installments') }}</th>
                        <th style="padding: 12px 16px; text-align: right; font-weight: 600; color: #475569; white-space: nowrap;">{{ __('Paid') }}</th>
                        <th style="padding: 12px 16px; text-align: right; font-weight: 600; color: #475569; white-space: nowrap;">{{ __('Remaining') }}</th>
                        <th style="padding: 12px 16px; text-align: center; font-weight: 600; color: #475569; white-space: nowrap;">{{ __('Progress') }}</th>
                        <th style="padding: 12px 16px; text-align: center; font-weight: 600; color: #475569; white-space: nowrap;">{{ __('Status') }}</th>
                        <th style="padding: 12px 16px; text-align: center; font-weight: 600; color: #475569; white-space: nowrap;">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($plans as $i => $plan)
                        @php
                            $student = $plan->studentFee->enrollment->student ?? null;
                            $fee     = $plan->studentFee;
                            $paid    = $plan->paid_amount;
                            $remaining = $plan->remaining_amount;
                            $progress = $plan->progress;
                            $paidCount = $plan->installments->where('status', 'paid')->count();

                            $statusColors = [
                                'active'    => ['bg' => '#ecfdf5', 'text' => '#065f46', 'dot' => '#10b981'],
                                'completed' => ['bg' => '#eff6ff', 'text' => '#1e40af', 'dot' => '#3b82f6'],
                                'cancelled' => ['bg' => '#fef2f2', 'text' => '#991b1b', 'dot' => '#ef4444'],
                                'defaulted' => ['bg' => '#fff7ed', 'text' => '#9a3412', 'dot' => '#f97316'],
                            ];
                            $sc = $statusColors[$plan->status] ?? $statusColors['active'];

                            $progressColor = $progress >= 75 ? '#10b981' : ($progress >= 40 ? '#f59e0b' : '#ef4444');
                        @endphp
                        <tr style="border-bottom: 1px solid #f1f5f9;"
                            onmouseover="this.style.background='#fafbfc'" onmouseout="this.style.background='transparent'">
                            <td style="padding: 12px 16px; color: #64748b;">{{ $plans->firstItem() + $i }}</td>
                            <td style="padding: 12px 16px;">
                                <div style="font-weight: 600; color: #1e293b;">{{ $student->full_name ?? '—' }}</div>
                                <div style="font-size: 0.75rem; color: #94a3b8;">{{ $student->student_id ?? '' }}</div>
                            </td>
                            <td style="padding: 12px 16px;">
                                <div style="font-weight: 500; color: #334155;">{{ $fee->feeCategory->name ?? '—' }}</div>
                                <div style="font-size: 0.75rem; color: #94a3b8;">{{ $fee->enrollment->academicSession->name ?? '' }}</div>
                            </td>
                            <td style="padding: 12px 16px; text-align: right; font-weight: 600; color: #1e293b;">{{ number_format($plan->total_amount, 0) }} <span style="font-size: 0.7rem; font-weight: 400; color: #94a3b8;">XAF</span></td>
                            <td style="padding: 12px 16px; text-align: center; color: #334155;">
                                <span style="font-weight: 600;">{{ $paidCount }}</span><span style="color: #94a3b8;">/{{ $plan->number_of_installments }}</span>
                            </td>
                            <td style="padding: 12px 16px; text-align: right; font-weight: 600; color: #059669;">{{ number_format($paid, 0) }}</td>
                            <td style="padding: 12px 16px; text-align: right; font-weight: 600; color: {{ $remaining > 0 ? '#dc2626' : '#059669' }};">{{ number_format($remaining, 0) }}</td>
                            <td style="padding: 12px 16px; text-align: center;">
                                <div style="display: flex; align-items: center; gap: 6px; justify-content: center;">
                                    <div style="width: 70px; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                                        <div style="width: {{ $progress }}%; height: 100%; background: {{ $progressColor }}; border-radius: 4px; transition: width 0.3s;"></div>
                                    </div>
                                    <span style="font-size: 0.75rem; font-weight: 600; color: {{ $progressColor }};">{{ $progress }}%</span>
                                </div>
                            </td>
                            <td style="padding: 12px 16px; text-align: center;">
                                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: {{ $sc['bg'] }}; color: {{ $sc['text'] }};">
                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: {{ $sc['dot'] }};"></span>
                                    {{ ucfirst($plan->status) }}
                                </span>
                            </td>
                            <td style="padding: 12px 16px; text-align: center;">
                                <div style="display: flex; align-items: center; gap: 6px; justify-content: center;">
                                    <a href="{{ route('admin.payment-plans.show', $plan) }}" title="{{ __('View Details') }}"
                                       style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; background: #eff6ff; color: #2563eb; border-radius: 6px; text-decoration: none;"
                                       onmouseover="this.style.background='#dbeafe'" onmouseout="this.style.background='#eff6ff'">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </a>
                                    @if($plan->status === 'active')
                                        <form method="POST" action="{{ route('admin.payment-plans.cancel', $plan) }}" onsubmit="return confirm('{{ __('Cancel this payment plan?') }}')" style="display:inline;">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" title="{{ __('Cancel Plan') }}"
                                                    style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; background: #fef2f2; color: #dc2626; border-radius: 6px; border: none; cursor: pointer;"
                                                    onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fef2f2'">
                                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" style="padding: 48px 16px; text-align: center;">
                                <div style="color: #94a3b8;">
                                    <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin: 0 auto 12px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                    <p style="font-size: 0.9rem; font-weight: 500; color: #64748b;">{{ __('No payment plans found.') }}</p>
                                    <p style="font-size: 0.8rem; color: #94a3b8; margin-top: 4px;">{{ __('Create a payment plan to get started.') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($plans->hasPages())
            <div style="padding: 12px 16px; border-top: 1px solid #e2e8f0;">
                {{ $plans->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
