@extends('layouts.admin')

@section('title', __('Manage Periods'))

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Manage Periods') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('Define daily period slots, breaks and lunch times') }}</p>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
        <div class="flex items-center">
            <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-green-800 text-sm font-medium">{{ session('success') }}</span>
        </div>
    </div>
    @endif

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <ul class="list-disc list-inside text-sm text-red-700">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Add New Slot Form --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Add New Period') }}</h3>
                <form method="POST" action="{{ route('admin.timetable.slots.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Period Number') }}</label>
                        <input type="number" name="period_number" min="1" value="{{ old('period_number', $slots->count() + 1) }}"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-slate-500 focus:border-slate-500" required>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Start Time') }}</label>
                            <input type="time" name="start_time" value="{{ old('start_time') }}"
                                   class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-slate-500 focus:border-slate-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('End Time') }}</label>
                            <input type="time" name="end_time" value="{{ old('end_time') }}"
                                   class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-slate-500 focus:border-slate-500" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Type') }}</label>
                        <select name="type" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-slate-500 focus:border-slate-500">
                            <option value="teaching">{{ __('Teaching') }}</option>
                            <option value="break">{{ __('Break') }}</option>
                            <option value="lunch">{{ __('Lunch') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Label') }} <span class="text-gray-400">{{ __('(optional)') }}</span></label>
                        <input type="text" name="label" value="{{ old('label') }}" placeholder="{{ __('e.g. Morning Break') }}"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-slate-500 focus:border-slate-500" maxlength="50">
                    </div>
                    <button type="submit" class="w-full px-4 py-2.5 bg-slate-800 text-white rounded-lg hover:bg-slate-700 font-medium text-sm transition">
                        {{ __('Add Period') }}
                    </button>
                </form>
            </div>
        </div>

        {{-- Existing Slots --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Daily Period Schedule') }}</h3>
                    <p class="text-sm text-gray-500">{{ $slots->count() }} period{{ $slots->count() !== 1 ? 's' : '' }} configured</p>
                </div>

                @if($slots->count() > 0)
                <div class="divide-y divide-gray-100">
                    @foreach($slots as $slot)
                    <div class="px-6 py-4 hover:bg-gray-50 transition" x-data="{ editing: false }">
                        {{-- Display Mode --}}
                        <div x-show="!editing" class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center text-sm font-bold shrink-0
                                    {{ $slot->type === 'teaching' ? 'bg-slate-800 text-white' : ($slot->type === 'break' ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700') }}">
                                    @if($slot->type === 'teaching')
                                        P{{ $slot->period_number }}
                                    @elseif($slot->type === 'break')
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    @else
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/></svg>
                                    @endif
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-gray-900">
                                        {{ $slot->label ?? ($slot->type === 'teaching' ? 'Period ' . $slot->period_number : ucfirst($slot->type)) }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ \Carbon\Carbon::parse($slot->start_time)->format('g:i A') }} — {{ \Carbon\Carbon::parse($slot->end_time)->format('g:i A') }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-1 text-xs font-medium rounded-full
                                    {{ $slot->type === 'teaching' ? 'bg-slate-100 text-slate-700' : ($slot->type === 'break' ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700') }}">
                                    {{ ucfirst($slot->type) }}
                                </span>
                                <button @click="editing = true" class="p-1.5 text-gray-400 hover:text-slate-700 rounded-lg hover:bg-gray-100 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <form method="POST" action="{{ route('admin.timetable.slots.destroy', $slot) }}" class="inline" onsubmit="return confirm('{{ __('Delete this period slot?') }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>

                        {{-- Edit Mode --}}
                        <form x-show="editing" method="POST" action="{{ route('admin.timetable.slots.update', $slot) }}" class="space-y-3">
                            @csrf @method('PUT')
                            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Period #') }}</label>
                                    <input type="number" name="period_number" value="{{ $slot->period_number }}" min="1"
                                           class="w-full text-sm rounded-md border-gray-300 focus:ring-1 focus:ring-slate-500" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Start') }}</label>
                                    <input type="time" name="start_time" value="{{ \Carbon\Carbon::parse($slot->start_time)->format('H:i') }}"
                                           class="w-full text-sm rounded-md border-gray-300 focus:ring-1 focus:ring-slate-500" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('End') }}</label>
                                    <input type="time" name="end_time" value="{{ \Carbon\Carbon::parse($slot->end_time)->format('H:i') }}"
                                           class="w-full text-sm rounded-md border-gray-300 focus:ring-1 focus:ring-slate-500" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Type') }}</label>
                                    <select name="type" class="w-full text-sm rounded-md border-gray-300 focus:ring-1 focus:ring-slate-500">
                                        <option value="teaching" {{ $slot->type === 'teaching' ? 'selected' : '' }}>{{ __('Teaching') }}</option>
                                        <option value="break" {{ $slot->type === 'break' ? 'selected' : '' }}>{{ __('Break') }}</option>
                                        <option value="lunch" {{ $slot->type === 'lunch' ? 'selected' : '' }}>{{ __('Lunch') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Label') }}</label>
                                    <input type="text" name="label" value="{{ $slot->label }}" maxlength="50"
                                           class="w-full text-sm rounded-md border-gray-300 focus:ring-1 focus:ring-slate-500">
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="submit" class="px-4 py-1.5 bg-slate-800 text-white rounded-md text-sm font-medium hover:bg-slate-700 transition">{{ __('Save') }}</button>
                                <button type="button" @click="editing = false" class="px-4 py-1.5 bg-gray-100 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-200 transition">{{ __('Cancel') }}</button>
                            </div>
                        </form>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="p-12 text-center">
                    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-1">{{ __('No Period Slots Defined') }}</h3>
                    <p class="text-gray-500 mb-2">{{ __('Start by adding your first period slot using the form on the left.') }}</p>
                    <p class="text-xs text-gray-400">{{ __('Typical Cameroon school: 6 teaching periods + 1 break + 1 lunch = 8 slots') }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
