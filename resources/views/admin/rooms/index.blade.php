@extends('layouts.admin')

@section('title', __('Classrooms'))
@section('breadcrumb', __('Academic > Classrooms'))

@section('content')
<div class="max-w-5xl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-lg font-semibold text-gray-700">{{ __('Classrooms') }}</h3>
            <p class="text-sm text-gray-500">{{ __('Manage physical rooms used for classes and activities') }}</p>
        </div>
        <button onclick="openAddModal()"
                class="px-4 py-2 bg-[#1e293b] text-white rounded-lg hover:bg-[#334155] transition text-sm font-medium flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('Add Classroom') }}
        </button>
    </div>

    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg mb-4 text-sm flex items-center gap-2">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Stats Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        @php
            $totalRooms = $rooms->count();
            $activeRooms = $rooms->where('is_active', true)->count();
            $totalCapacity = $rooms->where('is_active', true)->sum('capacity');
            $types = $rooms->where('is_active', true)->pluck('type')->filter()->unique()->count();
        @endphp
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-[#1e293b]">{{ $totalRooms }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ __('Total Rooms') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-emerald-600">{{ $activeRooms }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ __('Active') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-blue-600">{{ $totalCapacity }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ __('Total Capacity') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-purple-600">{{ $types }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ __('Room Types') }}</p>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase w-10">#</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Room') }}</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Building / Floor') }}</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">{{ __('Capacity') }}</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Type') }}</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">{{ __('Status') }}</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rooms as $index => $room)
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-5 py-3 text-gray-400 text-xs">{{ $index + 1 }}</td>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg {{ $room->is_active ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-400' }} flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            </div>
                            <span class="font-medium text-gray-900">{{ $room->name }}</span>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-gray-600">{{ $room->building_floor ?? '—' }}</td>
                    <td class="px-5 py-3 text-center">
                        @if($room->capacity)
                        <span class="inline-flex px-2 py-0.5 rounded bg-gray-100 text-xs font-medium text-gray-700">{{ $room->capacity }} {{ __('seats') }}</span>
                        @else
                        <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3">
                        @if($room->type)
                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700">{{ $room->type }}</span>
                        @else
                        <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $room->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $room->is_active ? __('Active') : __('Inactive') }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <div class="flex items-center justify-end gap-1.5">
                            <button onclick="openEditModal({{ $room->id }}, {{ Js::from($room->only(['name','building_floor','capacity','type','is_active'])) }})"
                                    class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 transition" title="{{ __('Edit') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <form method="POST" action="{{ route('admin.rooms.destroy', $room) }}" class="inline" onsubmit="return confirm('Delete {{ $room->name }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1.5 rounded-lg text-red-600 hover:bg-red-50 transition" title="{{ __('Delete') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-5 py-12 text-center">
                        <div class="flex flex-col items-center">
                            <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            <p class="text-gray-500 font-medium">{{ __('No classrooms yet') }}</p>
                            <p class="text-gray-400 text-xs mt-1">{{ __('Click "Add Classroom" to create your first room.') }}</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ================================================================ --}}
{{-- ADD MODAL                                                         --}}
{{-- ================================================================ --}}
<div id="addModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeAddModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md z-10 transform transition-all">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">{{ __('Add Classroom') }}</h3>
                <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form method="POST" action="{{ route('admin.rooms.store') }}" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Room No') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="{{ __('e.g. Room 1, Lab A') }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Building / Floor') }}</label>
                    <input type="text" name="building_floor" value="{{ old('building_floor') }}" placeholder="{{ __('e.g. Block A - 2nd Floor') }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Capacity') }}</label>
                    <input type="number" name="capacity" value="{{ old('capacity') }}" min="1" placeholder="{{ __('e.g. 50') }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Type') }}</label>
                    <select name="type"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            style="appearance: auto; -webkit-appearance: menulist;">
                        <option value="">{{ __('— Select Type —') }}</option>
                        <option value="Classroom" {{ old('type') === 'Classroom' ? 'selected' : '' }}>{{ __('Classroom') }}</option>
                        <option value="Laboratory" {{ old('type') === 'Laboratory' ? 'selected' : '' }}>{{ __('Laboratory') }}</option>
                        <option value="Computer Lab" {{ old('type') === 'Computer Lab' ? 'selected' : '' }}>{{ __('Computer Lab') }}</option>
                        <option value="Library" {{ old('type') === 'Library' ? 'selected' : '' }}>{{ __('Library') }}</option>
                        <option value="Workshop" {{ old('type') === 'Workshop' ? 'selected' : '' }}>{{ __('Workshop') }}</option>
                        <option value="Hall" {{ old('type') === 'Hall' ? 'selected' : '' }}>{{ __('Hall') }}</option>
                        <option value="Office" {{ old('type') === 'Office' ? 'selected' : '' }}>{{ __('Office') }}</option>
                        <option value="Other" {{ old('type') === 'Other' ? 'selected' : '' }}>{{ __('Other') }}</option>
                    </select>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeAddModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition">{{ __('Cancel') }}</button>
                    <button type="submit" class="px-5 py-2 bg-[#1e293b] text-white rounded-lg hover:bg-[#334155] transition text-sm font-medium">{{ __('Save Classroom') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ================================================================ --}}
{{-- EDIT MODAL                                                        --}}
{{-- ================================================================ --}}
<div id="editModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeEditModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md z-10 transform transition-all">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">{{ __('Edit Classroom') }}</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form id="editForm" method="POST" class="p-6 space-y-4">
                @csrf @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Room No') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="edit_name"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Building / Floor') }}</label>
                    <input type="text" name="building_floor" id="edit_building_floor"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Capacity') }}</label>
                    <input type="number" name="capacity" id="edit_capacity" min="1"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Type') }}</label>
                    <select name="type" id="edit_type"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            style="appearance: auto; -webkit-appearance: menulist;">
                        <option value="">{{ __('— Select Type —') }}</option>
                        <option value="Classroom">{{ __('Classroom') }}</option>
                        <option value="Laboratory">{{ __('Laboratory') }}</option>
                        <option value="Computer Lab">{{ __('Computer Lab') }}</option>
                        <option value="Library">{{ __('Library') }}</option>
                        <option value="Workshop">{{ __('Workshop') }}</option>
                        <option value="Hall">{{ __('Hall') }}</option>
                        <option value="Office">{{ __('Office') }}</option>
                        <option value="Other">{{ __('Other') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Status') }}</label>
                    <select name="is_active" id="edit_is_active"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            style="appearance: auto; -webkit-appearance: menulist;">
                        <option value="1">{{ __('Active') }}</option>
                        <option value="0">{{ __('Inactive') }}</option>
                    </select>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition">{{ __('Cancel') }}</button>
                    <button type="submit" class="px-5 py-2 bg-[#1e293b] text-white rounded-lg hover:bg-[#334155] transition text-sm font-medium">{{ __('Update Classroom') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const baseUrl = @json(route('admin.rooms.index'));

    function openAddModal() {
        document.getElementById('addModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeAddModal() {
        document.getElementById('addModal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    function openEditModal(id, data) {
        document.getElementById('editForm').action = baseUrl + '/' + id;
        document.getElementById('edit_name').value = data.name || '';
        document.getElementById('edit_building_floor').value = data.building_floor || '';
        document.getElementById('edit_capacity').value = data.capacity || '';
        document.getElementById('edit_type').value = data.type || '';
        document.getElementById('edit_is_active').value = data.is_active ? '1' : '0';
        document.getElementById('editModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    // Close modals on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { closeAddModal(); closeEditModal(); }
    });

    // Auto-open add modal if there were validation errors on store
    @if($errors->any() && old('_method') === null)
        openAddModal();
    @endif
</script>
@endpush
@endsection
