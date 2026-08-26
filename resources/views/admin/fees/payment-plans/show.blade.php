@extends('layouts.admin')

@section('title', __('Payment Plan Details'))
@section('breadcrumb', __('Fees > Payment Plans > Details'))

@section('content')
@php
    $fee     = $paymentPlan->studentFee;
    $student = $fee->enrollment->student ?? null;
    $enrollment = $fee->enrollment;
    $paid    = $paymentPlan->paid_amount;
    $remaining = $paymentPlan->remaining_amount;
    $progress = $paymentPlan->progress;

    $statusColors = [
        'active'    => ['bg' => '#ecfdf5', 'text' => '#065f46', 'dot' => '#10b981'],
        'completed' => ['bg' => '#eff6ff', 'text' => '#1e40af', 'dot' => '#3b82f6'],
        'cancelled' => ['bg' => '#fef2f2', 'text' => '#991b1b', 'dot' => '#ef4444'],
        'defaulted' => ['bg' => '#fff7ed', 'text' => '#9a3412', 'dot' => '#f97316'],
    ];
    $sc = $statusColors[$paymentPlan->status] ?? $statusColors['active'];
    $progressColor = $progress >= 75 ? '#10b981' : ($progress >= 40 ? '#f59e0b' : '#ef4444');
@endphp

<div style="max-width: 1000px;">

    {{-- Header --}}
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 700; color: #1e293b;">{{ __('Payment Plan Details') }}</h2>
            <p style="font-size: 0.825rem; color: #64748b; margin-top: 2px;">{{ $student->full_name ?? '—' }} — {{ $fee->feeCategory->name ?? '' }}</p>
        </div>
        <a href="{{ route('admin.payment-plans.index') }}"
           style="padding: 9px 18px; background: #f1f5f9; color: #475569; border-radius: 8px; font-size: 0.85rem; font-weight: 500; text-decoration: none;"
           onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
            {{ __('← Back to Plans') }}
        </a>
    </div>

    @if(session('success'))
        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 0.875rem;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 0.875rem;">{{ session('error') }}</div>
    @endif

    {{-- Summary Cards --}}
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
        <div style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <div style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Total Amount') }}</div>
            <div style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-top: 4px;">{{ number_format($paymentPlan->total_amount, 0) }} <span style="font-size: 0.7rem; font-weight: 400; color: #94a3b8;">XAF</span></div>
        </div>
        <div style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <div style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Paid') }}</div>
            <div style="font-size: 1.25rem; font-weight: 700; color: #059669; margin-top: 4px;">{{ number_format($paid, 0) }} <span style="font-size: 0.7rem; font-weight: 400; color: #94a3b8;">XAF</span></div>
        </div>
        <div style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <div style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Remaining') }}</div>
            <div style="font-size: 1.25rem; font-weight: 700; color: {{ $remaining > 0 ? '#dc2626' : '#059669' }}; margin-top: 4px;">{{ number_format($remaining, 0) }} <span style="font-size: 0.7rem; font-weight: 400; color: #94a3b8;">XAF</span></div>
        </div>
        <div style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <div style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Status') }}</div>
            <div style="margin-top: 4px;">
                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: {{ $sc['bg'] }}; color: {{ $sc['text'] }};">
                    <span style="width: 6px; height: 6px; border-radius: 50%; background: {{ $sc['dot'] }};"></span>
                    {{ ucfirst($paymentPlan->status) }}
                </span>
            </div>
        </div>
    </div>

    {{-- Progress Bar --}}
    <div style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 16px 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-size: 0.8rem; font-weight: 600; color: #475569;">{{ __('Progress') }}</span>
            <span style="font-size: 0.85rem; font-weight: 700; color: {{ $progressColor }};">{{ $progress }}%</span>
        </div>
        <div style="width: 100%; height: 10px; background: #e5e7eb; border-radius: 5px; overflow: hidden;">
            <div style="width: {{ $progress }}%; height: 100%; background: {{ $progressColor }}; border-radius: 5px; transition: width 0.3s;"></div>
        </div>
    </div>

    {{-- Plan Info --}}
    <div style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
        <h3 style="font-size: 0.95rem; font-weight: 600; color: #334155; margin-bottom: 16px;">{{ __('Plan Information') }}</h3>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; font-size: 0.85rem;">
            <div>
                <span style="color: #64748b;">{{ __('Student') }}:</span>
                <span style="font-weight: 600; color: #1e293b; margin-left: 4px;">{{ $student->full_name ?? '—' }} ({{ $student->student_id ?? '' }})</span>
            </div>
            <div>
                <span style="color: #64748b;">{{ __('Fee Category') }}:</span>
                <span style="font-weight: 600; color: #1e293b; margin-left: 4px;">{{ $fee->feeCategory->name ?? '—' }}</span>
            </div>
            <div>
                <span style="color: #64748b;">{{ __('Session') }}:</span>
                <span style="font-weight: 600; color: #1e293b; margin-left: 4px;">{{ $enrollment->academicSession->name ?? '—' }}</span>
            </div>
            <div>
                <span style="color: #64748b;">{{ __('Class') }}:</span>
                <span style="font-weight: 600; color: #1e293b; margin-left: 4px;">{{ $enrollment->classSection->name ?? '—' }}</span>
            </div>
            <div>
                <span style="color: #64748b;">{{ __('Instalments') }}:</span>
                <span style="font-weight: 600; color: #1e293b; margin-left: 4px;">{{ $paymentPlan->number_of_installments }}</span>
            </div>
            <div>
                <span style="color: #64748b;">{{ __('Late Fee') }}:</span>
                <span style="font-weight: 600; color: #1e293b; margin-left: 4px;">{{ $paymentPlan->late_fee_percentage }}%</span>
            </div>
            <div>
                <span style="color: #64748b;">{{ __('Grace Period') }}:</span>
                <span style="font-weight: 600; color: #1e293b; margin-left: 4px;">{{ $paymentPlan->grace_period_days }} {{ __('days') }}</span>
            </div>
            <div>
                <span style="color: #64748b;">{{ __('Created By') }}:</span>
                <span style="font-weight: 600; color: #1e293b; margin-left: 4px;">{{ $paymentPlan->creator->name ?? '—' }}</span>
            </div>
            <div>
                <span style="color: #64748b;">{{ __('Created') }}:</span>
                <span style="font-weight: 600; color: #1e293b; margin-left: 4px;">{{ $paymentPlan->created_at->format('d M Y') }}</span>
            </div>
        </div>
        @if($paymentPlan->notes)
            <div style="margin-top: 12px; padding: 10px 14px; background: #f8fafc; border-radius: 8px; font-size: 0.85rem; color: #475569;">
                <strong>{{ __('Notes') }}:</strong> {{ $paymentPlan->notes }}
            </div>
        @endif
    </div>

    {{-- Installments Table --}}
    <div style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
        <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0;">
            <h3 style="font-size: 0.95rem; font-weight: 600; color: #334155;">{{ __('Instalment Schedule') }}</h3>
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                    <th style="padding: 10px 14px; text-align: center; font-weight: 600; color: #475569;">#</th>
                    <th style="padding: 10px 14px; text-align: right; font-weight: 600; color: #475569;">{{ __('Amount') }}</th>
                    <th style="padding: 10px 14px; text-align: center; font-weight: 600; color: #475569;">{{ __('Due Date') }}</th>
                    <th style="padding: 10px 14px; text-align: right; font-weight: 600; color: #475569;">{{ __('Paid') }}</th>
                    <th style="padding: 10px 14px; text-align: right; font-weight: 600; color: #475569;">{{ __('Late Fee') }}</th>
                    <th style="padding: 10px 14px; text-align: center; font-weight: 600; color: #475569;">{{ __('Paid Date') }}</th>
                    <th style="padding: 10px 14px; text-align: center; font-weight: 600; color: #475569;">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($paymentPlan->installments as $inst)
                    @php
                        $instStatusColors = [
                            'pending' => ['bg' => '#fefce8', 'text' => '#854d0e'],
                            'paid'    => ['bg' => '#ecfdf5', 'text' => '#065f46'],
                            'partial' => ['bg' => '#fff7ed', 'text' => '#9a3412'],
                            'overdue' => ['bg' => '#fef2f2', 'text' => '#991b1b'],
                            'cancelled' => ['bg' => '#f1f5f9', 'text' => '#64748b'],
                        ];
                        $isc = $instStatusColors[$inst->status] ?? $instStatusColors['pending'];
                        $isOverdue = $inst->status === 'pending' && $inst->due_date->isPast();
                    @endphp
                    <tr style="border-bottom: 1px solid #f1f5f9; {{ $isOverdue ? 'background: #fff5f5;' : '' }}"
                        onmouseover="this.style.background='{{ $isOverdue ? '#fef2f2' : '#fafbfc' }}'" onmouseout="this.style.background='{{ $isOverdue ? '#fff5f5' : 'transparent' }}'">
                        <td style="padding: 10px 14px; text-align: center; font-weight: 600; color: #475569;">{{ $inst->installment_number }}</td>
                        <td style="padding: 10px 14px; text-align: right; font-weight: 600; color: #1e293b;">{{ number_format($inst->amount, 0) }}</td>
                        <td style="padding: 10px 14px; text-align: center; color: {{ $isOverdue ? '#dc2626' : '#334155' }}; font-weight: {{ $isOverdue ? '600' : '400' }};">{{ $inst->due_date->format('d M Y') }}</td>
                        <td style="padding: 10px 14px; text-align: right; font-weight: 600; color: #059669;">{{ $inst->paid_amount > 0 ? number_format($inst->paid_amount, 0) : '—' }}</td>
                        <td style="padding: 10px 14px; text-align: right; color: {{ $inst->late_fee_amount > 0 ? '#dc2626' : '#94a3b8' }};">{{ $inst->late_fee_amount > 0 ? number_format($inst->late_fee_amount, 0) : '—' }}</td>
                        <td style="padding: 10px 14px; text-align: center; color: #475569;">{{ $inst->paid_date ? $inst->paid_date->format('d M Y') : '—' }}</td>
                        <td style="padding: 10px 14px; text-align: center;">
                            <span style="display: inline-block; padding: 2px 10px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: {{ $isc['bg'] }}; color: {{ $isc['text'] }};">
                                {{ ucfirst($inst->status) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Cancel button --}}
    @if($paymentPlan->status === 'active')
        <div style="margin-top: 20px;">
            <form method="POST" action="{{ route('admin.payment-plans.cancel', $paymentPlan) }}" onsubmit="return confirm('{{ __('Are you sure you want to cancel this payment plan?') }}')">
                @csrf
                @method('PATCH')
                <button type="submit"
                        style="padding: 9px 20px; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer;"
                        onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fef2f2'">
                    {{ __('Cancel Payment Plan') }}
                </button>
            </form>
        </div>
    @endif
</div>
@endsection
