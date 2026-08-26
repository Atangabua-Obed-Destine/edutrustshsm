@extends('layouts.admin')
@section('title', __('Edit Role') . ' — ' . $role->display_name)

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2">
                <div class="w-1 h-8 bg-[#0ea5e9] rounded-full"></div>
                <h1 class="text-2xl font-bold text-gray-800">{{ __('Edit Role') }}</h1>
            </div>
            <p class="text-sm text-gray-500 mt-1 ml-3">{{ $role->display_name }} @if($role->is_system)<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200 ml-1"><svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>{{ __('System') }}</span>@endif</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.roles.index') }}"
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-gray-500 text-white rounded-lg hover:bg-gray-600 text-sm font-semibold shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                {{ __('Back') }}
            </a>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
            <ul class="list-disc list-inside text-sm space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.roles.update', $role) }}" method="POST" id="roleForm">
        @csrf
        @method('PUT')

        {{-- Role Details Card --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-[#0ea5e9]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                {{ __('Role Details') }}
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="display_name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Title') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="display_name" id="display_name" value="{{ old('display_name', $role->display_name) }}"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#0ea5e9] focus:border-transparent @error('display_name') border-red-400 @enderror"
                           placeholder="{{ __('e.g. Accountant, Class Teacher') }}" required
                           {{ $role->is_system ? 'readonly' : '' }}>
                    @if($role->is_system)
                        <p class="text-xs text-amber-600 mt-1">{{ __('System role names cannot be changed.') }}</p>
                    @endif
                    @error('display_name')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Description') }}</label>
                    <input type="text" name="description" id="description" value="{{ old('description', $role->description) }}"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#0ea5e9] focus:border-transparent"
                           placeholder="{{ __('Brief description of this role') }}">
                </div>
            </div>
        </div>

        {{-- Permissions Info Bar --}}
        <div class="bg-blue-50 border border-blue-200 rounded-xl px-6 py-4 mb-6 flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-6 text-sm">
                <span class="flex items-center gap-2 text-blue-800 font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                    {{ __('Total Permissions') }}: <strong>{{ $totalPermissions }}</strong>
                </span>
                <span class="text-blue-300">|</span>
                <span class="flex items-center gap-2 text-emerald-700 font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/></svg>
                    {{ __('Selected') }}: <strong id="selectedCount">0</strong>
                </span>
                <span class="text-blue-300">|</span>
                <span class="flex items-center gap-2 text-violet-700 font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    {{ __('Groups') }}: <strong>{{ count($permissionGroups) }}</strong>
                </span>
            </div>
            <button type="button" id="selectAllMaster"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-[#0ea5e9] text-white rounded-lg hover:bg-[#0284c7] text-sm font-semibold shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ __('Select All Permissions') }}
            </button>
        </div>

        {{-- Search Permissions --}}
        <div class="mb-6">
            <div class="relative">
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" id="permissionSearch" placeholder="{{ __('Search permissions or groups...') }}"
                       class="w-full pl-9 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#0ea5e9] focus:border-transparent">
            </div>
        </div>

        {{-- Permission Groups Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-8" id="permissionGroupsContainer">
            @foreach($permissionGroups as $groupName => $permissions)
            <div class="permission-group bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden" data-group="{{ strtolower($groupName) }}">
                {{-- Group Header --}}
                <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-gray-200">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-semibold text-gray-800">{{ $groupName }}</h3>
                        <span class="group-count inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold bg-[#0ea5e9] text-white">{{ count($permissions) }}</span>
                    </div>
                    <button type="button" class="group-select-all text-xs font-medium text-[#0ea5e9] hover:text-[#0284c7] transition"
                            data-group-name="{{ $groupName }}">
                        {{ __('Select All') }}
                    </button>
                </div>
                {{-- Permission Checkboxes --}}
                <div class="px-4 py-3 space-y-2">
                    @foreach($permissions as $permission)
                    <label class="permission-item flex items-center gap-3 py-1 px-2 rounded-lg hover:bg-gray-50 cursor-pointer transition" data-name="{{ strtolower($permission['display_name'] . ' ' . $permission['name']) }}">
                        <input type="checkbox" name="permissions[]" value="{{ $permission['id'] }}"
                               class="perm-checkbox w-4 h-4 text-[#0ea5e9] border-gray-300 rounded focus:ring-[#0ea5e9] focus:ring-offset-0"
                               data-group="{{ $groupName }}"
                               {{ in_array($permission['id'], old('permissions', $rolePermissionIds)) ? 'checked' : '' }}>
                        <span class="text-sm text-gray-700">{{ $permission['display_name'] }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>

        {{-- Submit Button --}}
        <div class="flex items-center justify-end gap-3 pb-6">
            <a href="{{ route('admin.roles.index') }}" class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-semibold transition">
                {{ __('Cancel') }}
            </a>
            <button type="submit" id="submitBtn"
                    class="inline-flex items-center gap-2 px-8 py-2.5 bg-[#0ea5e9] text-white rounded-lg hover:bg-[#0284c7] text-sm font-semibold shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                {{ __('Update Role') }}
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const allCheckboxes = document.querySelectorAll('.perm-checkbox');
    const selectedCountEl = document.getElementById('selectedCount');
    const masterBtn = document.getElementById('selectAllMaster');
    const searchInput = document.getElementById('permissionSearch');
    let allSelected = false;

    function updateCount() {
        const checked = document.querySelectorAll('.perm-checkbox:checked').length;
        selectedCountEl.textContent = checked;

        allSelected = checked === allCheckboxes.length;
        masterBtn.innerHTML = allSelected
            ? '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> {{ __("Deselect All Permissions") }}'
            : '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> {{ __("Select All Permissions") }}';

        document.querySelectorAll('.group-select-all').forEach(btn => {
            const groupName = btn.dataset.groupName;
            const groupCbs = document.querySelectorAll(`.perm-checkbox[data-group="${groupName}"]`);
            const checkedCount = [...groupCbs].filter(cb => cb.checked).length;
            btn.textContent = checkedCount === groupCbs.length ? '{{ __("Deselect All") }}' : '{{ __("Select All") }}';
        });
    }

    masterBtn.addEventListener('click', function () {
        allSelected = !allSelected;
        allCheckboxes.forEach(cb => cb.checked = allSelected);
        updateCount();
    });

    document.querySelectorAll('.group-select-all').forEach(btn => {
        btn.addEventListener('click', function () {
            const groupName = this.dataset.groupName;
            const groupCbs = document.querySelectorAll(`.perm-checkbox[data-group="${groupName}"]`);
            const allGroupChecked = [...groupCbs].every(cb => cb.checked);
            groupCbs.forEach(cb => cb.checked = !allGroupChecked);
            updateCount();
        });
    });

    allCheckboxes.forEach(cb => cb.addEventListener('change', updateCount));

    searchInput.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('.permission-group').forEach(group => {
            const groupName = group.dataset.group;
            let visibleCount = 0;

            group.querySelectorAll('.permission-item').forEach(item => {
                const name = item.dataset.name || '';
                const show = !q || name.includes(q) || groupName.includes(q);
                item.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            });

            group.style.display = visibleCount > 0 ? '' : 'none';
        });
    });

    updateCount();
});
</script>
@endpush
