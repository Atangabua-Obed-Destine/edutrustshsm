@extends('layouts.admin')

@section('title', $house->name)
@section('breadcrumb', __('Students > Boarding > :house', ['house' => $house->name]))

@section('content')
@php
    $genderLabel = ['boys' => __('Boys'), 'girls' => __('Girls'), 'mixed' => __('Mixed')];
    $beds = $house->rooms->where('is_active', true)->sum('beds');
    $used = $house->rooms->sum(fn ($r) => $r->occupants->count());
@endphp
<div class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-700">{{ $house->name }}</h3>
            <p class="text-sm text-gray-500">{{ $genderLabel[$house->gender] ?? $house->gender }} · {{ __(':used of :beds beds taken', ['used' => $used, 'beds' => $beds]) }}{{ $house->is_active ? '' : ' · '.__('closed') }}
                · {{ $house->houseMaster ? __('House master: :name', ['name' => $house->houseMaster->first_name.' '.$house->houseMaster->last_name]) : __('No house master named') }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.boarding.index') }}" class="text-sm border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-2 rounded-lg">{{ __('All houses') }}</a>
            <a href="{{ route('admin.boarding.print', $house) }}" class="text-sm border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-2 rounded-lg">{{ __('House list (PDF)') }}</a>
        </div>
    </div>

    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ $errors->first() }}</div>
    @endif

    @forelse($house->rooms as $room)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden {{ $room->is_active ? '' : 'opacity-60' }}">
            <div class="px-5 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                <h4 class="text-sm font-semibold text-gray-700">{{ __('Room :room', ['room' => $room->name]) }}
                    <span class="font-normal text-gray-500">· {{ __(':used of :beds beds', ['used' => $room->occupants->count(), 'beds' => $room->beds]) }}{{ $room->is_active ? '' : ' · '.__('closed') }}</span></h4>
                @can('boarding.manage')
                <details class="text-sm">
                    <summary class="cursor-pointer text-blue-600">{{ __('Edit room') }}</summary>
                    <div class="mt-2 flex flex-wrap items-end gap-2">
                        <form method="POST" action="{{ route('admin.boarding.rooms.update', $room) }}" class="flex flex-wrap items-end gap-2">
                            @csrf @method('PUT')
                            <input name="name" value="{{ $room->name }}" required maxlength="50" aria-label="{{ __('Room name') }}" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-28">
                            <input name="beds" type="number" min="1" max="200" value="{{ $room->beds }}" required aria-label="{{ __('Beds') }}" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-20">
                            <label class="flex items-center gap-1 text-xs text-gray-600"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($room->is_active)> {{ __('Open') }}</label>
                            <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-3 py-1.5 rounded-lg">{{ __('Save') }}</button>
                        </form>
                        @if($room->allocations()->doesntExist())
                        <form method="POST" action="{{ route('admin.boarding.rooms.destroy', $room) }}" onsubmit="return confirm(@js(__('Delete this room?')))">
                            @csrf @method('DELETE')
                            <button class="border border-red-300 text-red-700 hover:bg-red-50 text-sm px-3 py-1.5 rounded-lg">{{ __('Delete') }}</button>
                        </form>
                        @endif
                    </div>
                </details>
                @endcan
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100">
                    @forelse($room->occupants->sortBy('bed', SORT_NATURAL) as $a)
                    <tr>
                        <td class="px-4 py-2 w-20 text-gray-500">{{ $a->bed ? __('Bed :bed', ['bed' => $a->bed]) : '—' }}</td>
                        <td class="px-4 py-2">
                            <a href="{{ route('admin.students.show', $a->student_id) }}" class="text-blue-600 hover:underline">{{ $a->student?->full_name }}</a>
                            <span class="text-xs text-gray-400">{{ $a->student?->student_id }}</span>
                            @unless($boarders->has($a->student_id))
                                <span class="ml-1 text-xs text-red-700 bg-red-50 rounded px-1.5 py-0.5">{{ __('not a boarder this year') }}</span>
                            @endunless
                        </td>
                        <td class="px-4 py-2 text-gray-500">{{ $boarders[$a->student_id]->class ?? '' }}</td>
                        <td class="px-4 py-2 text-gray-500 whitespace-nowrap">{{ __('since :date', ['date' => $a->start_date->format('d/m/Y')]) }}</td>
                        <td class="px-4 py-2 text-right">
                            @can('boarding.allocate')
                            <details class="inline-block text-left">
                                <summary class="cursor-pointer text-blue-600 text-sm">{{ __('Move or free') }}</summary>
                                <div class="mt-2 space-y-2">
                                    @php $moves = $rooms->filter(fn ($r) => $r['room']->id !== $room->id && $r['house']->takes($a->student?->gender)); @endphp
                                    @if($moves->isNotEmpty())
                                    <form method="POST" action="{{ route('admin.boarding.allocate') }}" class="flex flex-wrap gap-2">
                                        @csrf
                                        <input type="hidden" name="student_id" value="{{ $a->student_id }}">
                                        <select name="boarding_room_id" aria-label="{{ __('Move to') }}" class="border border-gray-300 rounded-lg px-2 py-1 text-sm">
                                            @foreach($moves as $o)<option value="{{ $o['room']->id }}">{{ $o['house']->name }} · {{ $o['room']->name }}</option>@endforeach
                                        </select>
                                        <input name="bed" placeholder="{{ __('Bed') }}" aria-label="{{ __('Bed') }}" class="border border-gray-300 rounded-lg px-2 py-1 text-sm w-20">
                                        <button class="bg-blue-600 text-white text-sm px-3 py-1 rounded-lg">{{ __('Move') }}</button>
                                    </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.boarding.check-out', $a) }}" class="flex flex-wrap gap-2">
                                        @csrf
                                        <input name="reason" required maxlength="255" placeholder="{{ __('Why the bed is freed') }}" aria-label="{{ __('Why the bed is freed') }}" class="border border-gray-300 rounded-lg px-2 py-1 text-sm w-48">
                                        <button class="border border-red-300 text-red-700 hover:bg-red-50 text-sm px-3 py-1 rounded-lg">{{ __('Free the bed') }}</button>
                                    </form>
                                </div>
                            </details>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td class="px-4 py-3 text-gray-400" colspan="5">{{ __('Empty.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl border border-gray-200 p-8 text-center text-sm text-gray-500">{{ __('This house has no rooms yet.') }}</div>
    @endforelse

    @can('boarding.manage')
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <form method="POST" action="{{ route('admin.boarding.rooms.store', $house) }}" class="bg-white rounded-xl border border-gray-200 p-5">
            @csrf
            <h4 class="text-sm font-semibold text-gray-700">{{ __('Add a room') }}</h4>
            <div class="mt-3 flex flex-wrap items-end gap-3">
                <div>
                    <label for="room-name" class="block text-xs font-medium text-gray-600">{{ __('Room name or number') }}</label>
                    <input id="room-name" name="name" required maxlength="50" class="mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm w-40">
                </div>
                <div>
                    <label for="room-beds" class="block text-xs font-medium text-gray-600">{{ __('Beds') }}</label>
                    <input id="room-beds" name="beds" type="number" min="1" max="200" value="8" required class="mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm w-24">
                </div>
                <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg">{{ __('Add room') }}</button>
            </div>
        </form>

        <form method="POST" action="{{ route('admin.boarding.houses.update', $house) }}" class="bg-white rounded-xl border border-gray-200 p-5">
            @csrf @method('PUT')
            <h4 class="text-sm font-semibold text-gray-700">{{ __('The house') }}</h4>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="h-name" class="block text-xs font-medium text-gray-600">{{ __('Name') }}</label>
                    <input id="h-name" name="name" value="{{ $house->name }}" required maxlength="100" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="h-gender" class="block text-xs font-medium text-gray-600">{{ __('Takes') }}</label>
                    <select id="h-gender" name="gender" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        @foreach($genderLabel as $value => $label)<option value="{{ $value }}" @selected($house->gender === $value)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="h-master" class="block text-xs font-medium text-gray-600">{{ __('House master or mistress') }}</label>
                    <select id="h-master" name="house_master_id" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">—</option>
                        @foreach($staff as $person)<option value="{{ $person->id }}" @selected($house->house_master_id === $person->id)>{{ $person->first_name }} {{ $person->last_name }}</option>@endforeach
                    </select>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-600 mt-5"><input type="checkbox" name="is_active" value="1" @checked($house->is_active)> {{ __('Open') }}</label>
            </div>
            <div class="mt-3 flex items-center gap-2">
                <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
    @if($house->allocations()->doesntExist())
    <form method="POST" action="{{ route('admin.boarding.houses.destroy', $house) }}" onsubmit="return confirm(@js(__('Delete this house and its rooms?')))">
        @csrf @method('DELETE')
        <button class="text-sm text-red-700 hover:underline">{{ __('Delete this house') }}</button>
    </form>
    @endif
    @endcan
</div>
@endsection
