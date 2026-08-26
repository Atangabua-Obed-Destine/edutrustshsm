@extends('parent.layouts.app')
@section('title', __('My Children'))
@section('heading', __('My Children'))

@section('content')
@php
    use Illuminate\Support\Number;
    $currency = (\App\Models\SchoolSetting::current()->currency ?? 'FCFA');
@endphp

<div style="margin-bottom: 22px;">
    <h2 style="font-size: 1.15rem; font-weight: 700; color: #0f172a;">{{ __('Welcome, :name', ['name' => $guardian->display_name]) }}</h2>
    <p style="font-size: 0.85rem; color: #64748b; margin-top: 3px;">{{ __('Select a child to view their academic and financial records.') }}</p>
</div>

@if($children->isEmpty())
    <div class="pp-card" style="padding: 40px; text-align: center;">
        <div style="width: 56px; height: 56px; border-radius: 50%; background: #f1f5f9; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 14px;">
            <svg width="28" height="28" style="color:#94a3b8;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        </div>
        <h3 style="font-size: 1rem; font-weight: 600; color: #334155;">{{ __('No children linked yet') }}</h3>
        <p style="font-size: 0.84rem; color: #64748b; margin-top: 6px; max-width: 380px; margin-left:auto; margin-right:auto;">
            {{ __('Your account is not yet linked to any student. Please contact the school office to link your children.') }}
        </p>
    </div>
@else
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 18px;">
        @foreach($children as $child)
            @php
                $snap = $feeSnapshots[$child->id] ?? ['balance' => 0, 'net' => 0, 'paid' => 0, 'percent' => 0];
                $enr = $child->currentEnrollment;
                $className = $enr?->classSection?->name ?? $enr?->classSection?->form?->name ?? __('Not enrolled');
            @endphp
            <a href="{{ route('parent.student.show', $child->id) }}" class="pp-card" style="display: block; padding: 0; overflow: hidden; transition: transform 0.15s, box-shadow 0.15s;"
               onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 24px rgba(0,0,0,0.1)'"
               onmouseout="this.style.transform='none'; this.style.boxShadow='0 1px 3px rgba(0,0,0,0.07)'">
                {{-- Header --}}
                <div style="padding: 20px; display: flex; align-items: center; gap: 14px; background: linear-gradient(135deg, #0f172a 0%, #134e4a 100%);">
                    @if($child->photo)
                        <img src="{{ asset('storage/' . $child->photo) }}" alt="" style="width: 54px; height: 54px; border-radius: 12px; object-fit: cover; border: 2px solid rgba(255,255,255,0.2);">
                    @else
                        <div style="width: 54px; height: 54px; border-radius: 12px; background: rgba(20,184,166,0.25); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; font-weight: 700; color: #5eead4;">{{ mb_substr($child->first_name, 0, 1) }}{{ mb_substr($child->last_name, 0, 1) }}</div>
                    @endif
                    <div style="min-width: 0;">
                        <div style="font-size: 0.98rem; font-weight: 700; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $child->first_name }} {{ $child->last_name }}</div>
                        <div style="font-size: 0.72rem; color: #94a3b8; margin-top: 2px;">{{ $child->student_id }}</div>
                    </div>
                </div>
                {{-- Body --}}
                <div style="padding: 16px 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <span style="font-size: 0.75rem; color: #64748b;">{{ __('Class') }}</span>
                        <span style="font-size: 0.82rem; font-weight: 600; color: #1e293b;">{{ $className }}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span style="font-size: 0.75rem; color: #64748b;">{{ __('Fee Balance') }}</span>
                        <span style="font-size: 0.82rem; font-weight: 700; color: {{ $snap['balance'] > 0 ? '#dc2626' : '#16a34a' }};">
                            {{ number_format($snap['balance'], 0) }} {{ $currency }}
                        </span>
                    </div>
                    {{-- Fee progress bar --}}
                    <div style="height: 6px; background: #f1f5f9; border-radius: 99px; overflow: hidden;">
                        <div style="height: 100%; width: {{ $snap['percent'] }}%; background: {{ $snap['percent'] >= 100 ? '#16a34a' : '#14b8a6' }}; border-radius: 99px;"></div>
                    </div>
                    <div style="display:flex; justify-content: space-between; margin-top: 6px;">
                        <span style="font-size: 0.68rem; color: #94a3b8;">{{ $snap['percent'] }}% {{ __('paid') }}</span>
                        <span style="font-size: 0.72rem; color: #14b8a6; font-weight: 600;">{{ __('View details') }} →</span>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
@endif
@endsection
