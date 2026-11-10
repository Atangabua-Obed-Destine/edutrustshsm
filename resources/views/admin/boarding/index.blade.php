@extends('layouts.admin')

@section('title', __('Boarding'))
@section('breadcrumb', __('Students > Boarding'))

@section('content')
@php
    $genderLabel = ['boys' => __('Boys'), 'girls' => __('Girls'), 'mixed' => __('Mixed')];
    $roomOptions = fn ($student) => $rooms->filter(fn ($r) => $r['house']->takes($student->gender));
@endphp
<div class="space-y-4">
    <div>
        <h3 class="text-lg font-semibold text-gray-700">{{ __('Boarding') }}</h3>
        <p class="text-sm text-gray-500">{{ __('The boarding houses, their rooms and beds, and who sleeps where. A student is a boarder when their enrolment says so; that is also what charges the boarding fee.') }}</p>
    </div>

    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        @foreach([
            [__('Houses'), $totals['houses'], 'text-gray-800'],
            [__('Beds'), $totals['beds'], 'text-gray-800'],
            [__('Occupied'), $totals['occupied'], 'text-blue-700'],
            [__('Free beds'), $totals['free'], $totals['free'] > 0 ? 'text-emerald-700' : 'text-red-700'],
            [__('Boarders this year'), $totals['boarders'], 'text-gray-800'],
        ] as [$label, $value, $tone])
        <div class="bg-white rounded-xl border border-gray-200 p-4 {{ $loop->last ? 'col-span-2 lg:col-span-1' : '' }}">
            <p class="text-xs text-gray-500 uppercase tracking-wide">{{ $label }}</p>
            <p class="text-2xl font-bold {{ $tone }}">{{ $value }}</p>
        </div>
        @endforeach
    </div>

    {{-- Boarders the school charges for boarding but has given no bed --}}
    @if($needBed->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-amber-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-amber-100 bg-amber-50">
            <h4 class="text-sm font-semibold text-amber-900">{{ trans_choice('{1} :count boarder has no bed yet|[2,*] :count boarders have no bed yet', $needBed->count(), ['count' => $needBed->count()]) }}</h4>
            <p class="text-xs text-amber-800">{{ __('Their enrolment says they board this year, but no room has them.') }}</p>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-100">
                @foreach($needBed as $student)
                <tr>
                    <td class="px-4 py-2"><a href="{{ route('admin.students.show', $student) }}" class="text-blue-600 hover:underline">{{ $student->full_name }}</a> <span class="text-xs text-gray-400">{{ $student->student_id }}</span></td>
                    <td class="px-4 py-2 text-gray-500">{{ $student->class_name }}</td>
                    <td class="px-4 py-2 text-gray-500">{{ $student->gender === 'female' ? __('Girl') : __('Boy') }}</td>
                    <td class="px-4 py-2">
                        @can('boarding.allocate')
                            @php $options = $roomOptions($student); @endphp
                            @if($options->isEmpty())
                                <span class="text-xs text-red-700">{{ __('No free bed in a house that takes them.') }}</span>
                            @else
                            <form method="POST" action="{{ route('admin.boarding.allocate') }}" class="flex flex-wrap items-center gap-2 justify-end">
                                @csrf
                                <input type="hidden" name="student_id" value="{{ $student->id }}">
                                <label class="sr-only" for="room-{{ $student->id }}">{{ __('Room') }}</label>
                                <select id="room-{{ $student->id }}" name="boarding_room_id" required class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                                    @foreach($options as $o)
                                        <option value="{{ $o['room']->id }}">{{ $o['house']->name }} · {{ $o['room']->name }} ({{ trans_choice('{1} :count bed free|[2,*] :count beds free', $o['free'], ['count' => $o['free']]) }})</option>
                                    @endforeach
                                </select>
                                <label class="sr-only" for="bed-{{ $student->id }}">{{ __('Bed') }}</label>
                                <input id="bed-{{ $student->id }}" name="bed" placeholder="{{ __('Bed (optional)') }}" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-32">
                                <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-3 py-1.5 rounded-lg">{{ __('Give a bed') }}</button>
                            </form>
                            @endif
                        @endcan
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
    @endif

    {{-- Holding a bed without being a boarder: moved to day, left, or not enrolled this year --}}
    @if($shouldLeave->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-red-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-red-100 bg-red-50">
            <h4 class="text-sm font-semibold text-red-900">{{ trans_choice('{1} :count student holds a bed but is not a boarder this year|[2,*] :count students hold a bed but are not boarders this year', $shouldLeave->count(), ['count' => $shouldLeave->count()]) }}</h4>
            <p class="text-xs text-red-800">{{ __('They may have become day students or not be enrolled this year. The bed stays theirs until the house master frees it.') }}</p>
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-100">
                @foreach($shouldLeave as $a)
                <tr>
                    <td class="px-4 py-2">{{ $a->student?->full_name }} <span class="text-xs text-gray-400">{{ $a->student?->student_id }}</span></td>
                    <td class="px-4 py-2 text-gray-500">{{ $a->room->house->name }} · {{ $a->room->name }}{{ $a->bed ? ' · '.__('bed :bed', ['bed' => $a->bed]) : '' }}</td>
                    <td class="px-4 py-2 text-right">
                        @can('boarding.allocate')
                        <form method="POST" action="{{ route('admin.boarding.check-out', $a) }}" class="inline-flex gap-2">
                            @csrf
                            <input type="hidden" name="reason" value="{{ __('No longer a boarder') }}">
                            <button class="border border-red-300 text-red-700 hover:bg-red-50 text-sm px-3 py-1.5 rounded-lg">{{ __('Free the bed') }}</button>
                        </form>
                        @endcan
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- The houses --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse($houses as $house)
            @php
                $beds = $house->rooms->where('is_active', true)->sum('beds');
                $used = $house->rooms->sum(fn ($r) => $r->occupants->count());
            @endphp
            <a href="{{ route('admin.boarding.show', $house) }}" class="block bg-white rounded-xl border border-gray-200 p-5 hover:border-blue-400 {{ $house->is_active ? '' : 'opacity-60' }}">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold text-gray-800">{{ $house->name }}</p>
                        <p class="text-xs text-gray-500">{{ $genderLabel[$house->gender] ?? $house->gender }} · {{ trans_choice('{0} no rooms yet|{1} :count room|[2,*] :count rooms', $house->rooms->count(), ['count' => $house->rooms->count()]) }}{{ $house->is_active ? '' : ' · '.__('closed') }}</p>
                    </div>
                    <span class="text-sm font-semibold {{ $beds > 0 && $used >= $beds ? 'text-red-700' : 'text-gray-700' }}">{{ $used }} / {{ $beds }}</span>
                </div>
                <div class="mt-3 h-2 bg-gray-100 rounded-full overflow-hidden"><div class="h-2 {{ $beds > 0 && $used >= $beds ? 'bg-red-500' : 'bg-blue-500' }}" style="width: {{ $beds > 0 ? min(100, round($used / $beds * 100)) : 0 }}%"></div></div>
                <p class="mt-2 text-xs text-gray-500">{{ $house->houseMaster ? __('House master: :name', ['name' => $house->houseMaster->first_name.' '.$house->houseMaster->last_name]) : __('No house master named') }}</p>
            </a>
        @empty
            <div class="md:col-span-2 xl:col-span-3 bg-white rounded-xl border border-gray-200 p-8 text-center text-sm text-gray-500">{{ __('No boarding house yet. Add the first below.') }}</div>
        @endforelse
    </div>

    @can('boarding.manage')
    <form method="POST" action="{{ route('admin.boarding.houses.store') }}" class="bg-white rounded-xl border border-gray-200 p-5">
        @csrf
        <h4 class="text-sm font-semibold text-gray-700">{{ __('Add a house') }}</h4>
        <div class="mt-3 grid grid-cols-1 md:grid-cols-4 gap-3">
            <div>
                <label for="house-name" class="block text-xs font-medium text-gray-600">{{ __('Name') }}</label>
                <input id="house-name" name="name" required maxlength="100" value="{{ old('name') }}" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label for="house-gender" class="block text-xs font-medium text-gray-600">{{ __('Takes') }}</label>
                <select id="house-gender" name="gender" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach($genderLabel as $value => $label)<option value="{{ $value }}" @selected(old('gender') === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="house-master" class="block text-xs font-medium text-gray-600">{{ __('House master or mistress') }}</label>
                <select id="house-master" name="house_master_id" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">—</option>
                    @foreach($staff as $person)<option value="{{ $person->id }}">{{ $person->first_name }} {{ $person->last_name }}</option>@endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button class="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg">{{ __('Add house') }}</button>
            </div>
        </div>
    </form>
    @endcan
</div>
@endsection
