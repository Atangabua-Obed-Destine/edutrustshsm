{{-- Single schedule entry row --}}
@php $inputStyle = 'width:100%; padding:8px 10px; border:1px solid #d1d5db; border-radius:8px; outline:none; background:#fff; font-size:0.82rem; appearance:auto; -webkit-appearance:menulist;'; @endphp
@php $timeStyle  = 'width:100%; padding:8px 10px; border:1px solid #d1d5db; border-radius:8px; outline:none; background:#fff; font-size:0.82rem;'; @endphp

<div class="entry-row" style="display:grid; grid-template-columns:3fr 2fr 2fr 1.5fr 1.5fr 40px; gap:10px; align-items:center; padding:10px 12px; background:#f8fafc; border-radius:10px; border:1px solid #e2e8f0;">
    {{-- Subject --}}
    <div>
        <select name="entries[{{ $idx }}][subject_id]" required
                style="{{ $inputStyle }}"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 2px rgba(59,130,246,.15)';"
                onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none';">
            <option value="">{{ __('Select') }}</option>
            @foreach($subjects as $subj)
            <option value="{{ $subj->id }}" {{ $entry && $entry->subject_id == $subj->id ? 'selected' : '' }}>{{ $subj->name }} ({{ $subj->code }})</option>
            @endforeach
        </select>
    </div>
    {{-- Teacher --}}
    <div>
        <select name="entries[{{ $idx }}][teacher_id]" required
                style="{{ $inputStyle }}"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 2px rgba(59,130,246,.15)';"
                onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none';">
            <option value="">{{ __('Select') }}</option>
            @foreach($teachers as $t)
            <option value="{{ $t->id }}" {{ $entry && $entry->teacher_id == $t->id ? 'selected' : '' }}>{{ $t->full_name }}</option>
            @endforeach
        </select>
    </div>
    {{-- Room --}}
    <div>
        <select name="entries[{{ $idx }}][room_id]"
                style="{{ $inputStyle }}"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 2px rgba(59,130,246,.15)';"
                onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none';">
            <option value="">{{ __('Select') }}</option>
            @foreach($rooms as $r)
            <option value="{{ $r->id }}" {{ $entry && $entry->room_id == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
            @endforeach
        </select>
    </div>
    {{-- Time From --}}
    <div>
        <input type="time" name="entries[{{ $idx }}][start_time]" required
               value="{{ $entry && $entry->start_time ? \Carbon\Carbon::parse($entry->start_time)->format('H:i') : '' }}"
               style="{{ $timeStyle }}"
               onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 2px rgba(59,130,246,.15)';"
               onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none';">
    </div>
    {{-- Time To --}}
    <div>
        <input type="time" name="entries[{{ $idx }}][end_time]" required
               value="{{ $entry && $entry->end_time ? \Carbon\Carbon::parse($entry->end_time)->format('H:i') : '' }}"
               style="{{ $timeStyle }}"
               onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 2px rgba(59,130,246,.15)';"
               onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none';">
    </div>
    {{-- Remove --}}
    <div style="display:flex; justify-content:center;">
        <button type="button" onclick="removeRow(this)" title="{{ __('Remove') }}"
                style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border:1px solid #fca5a5; color:#dc2626; background:transparent; border-radius:8px; cursor:pointer;"
                onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='transparent'">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </button>
    </div>
</div>
