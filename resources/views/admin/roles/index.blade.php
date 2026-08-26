@extends('layouts.admin')
@section('title', __('Roles & Permissions'))

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2">
                <div class="w-1 h-8 bg-[#0ea5e9] rounded-full"></div>
                <h1 class="text-2xl font-bold text-gray-800">{{ __('Roles & Permissions') }}</h1>
            </div>
            <p class="text-sm text-gray-500 mt-1 ml-3">{{ __('Manage system roles and their access permissions') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.roles.create') }}"
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#0ea5e9] text-white rounded-lg hover:bg-[#0284c7] text-sm font-semibold shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('Add New') }}
            </a>
            <a href="{{ route('admin.roles.index') }}"
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#22d3ee] text-white rounded-lg hover:bg-[#06b6d4] text-sm font-semibold shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                {{ __('Refresh') }}
            </a>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg flex items-center gap-2">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900">{{ $roles->count() }}</p>
                    <p class="text-xs text-gray-500">{{ __('Total Roles') }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900">{{ $roles->where('is_system', true)->count() }}</p>
                    <p class="text-xs text-gray-500">{{ __('System Roles') }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-violet-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900">{{ $roles->where('is_system', false)->count() }}</p>
                    <p class="text-xs text-gray-500">{{ __('Custom Roles') }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900">{{ \App\Models\Permission::count() }}</p>
                    <p class="text-xs text-gray-500">{{ __('Total Permissions') }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Roles Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        {{-- Table Controls --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div class="flex items-center gap-2 text-sm text-gray-600">
                {{ __('Show') }}
                <select id="perPageSelect" class="border border-gray-300 rounded-lg px-2 py-1 text-sm focus:ring-2 focus:ring-[#0ea5e9] focus:border-transparent" onchange="filterTable()">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
                {{ __('entries') }}
            </div>
            <div class="relative">
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" id="roleSearch" placeholder="{{ __('Search...') }}" class="pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#0ea5e9] focus:border-transparent w-56" oninput="filterTable()">
            </div>
        </div>

        <table class="w-full text-sm" id="rolesTable">
            <thead>
                <tr class="bg-[#0ea5e9] text-white">
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider w-16 cursor-pointer select-none" onclick="sortTable(0)">#
                        <svg class="w-3 h-3 inline ml-1 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider cursor-pointer select-none" onclick="sortTable(1)">{{ __('Title') }}
                        <svg class="w-3 h-3 inline ml-1 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
                    </th>
                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider w-32">{{ __('Permissions') }}</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider w-24">{{ __('Users') }}</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider w-24">{{ __('Type') }}</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider w-48">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100" id="rolesBody">
                @forelse($roles as $idx => $role)
                <tr class="role-row hover:bg-gray-50 transition" data-search="{{ strtolower($role->display_name . ' ' . $role->name . ' ' . $role->description) }}">
                    <td class="px-6 py-4 text-gray-500 font-medium">{{ $idx + 1 }}</td>
                    <td class="px-6 py-4">
                        <div class="font-semibold text-gray-900">{{ $role->display_name }}</div>
                        @if($role->description)
                            <div class="text-xs text-gray-400 mt-0.5 max-w-md truncate">{{ $role->description }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                            {{ $role->permissions_count }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold {{ $role->users_count > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-50 text-gray-500 border border-gray-200' }}">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            {{ $role->users_count }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($role->is_system)
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                {{ __('System') }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-cyan-50 text-cyan-700 border border-cyan-200">
                                {{ __('Custom') }}
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-center gap-2">
                            <a href="{{ route('admin.roles.show', $role) }}" title="{{ __('View') }}"
                               class="inline-flex items-center justify-center w-9 h-9 bg-[#0ea5e9] text-white rounded-lg hover:bg-[#0284c7] transition shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                            <a href="{{ route('admin.roles.edit', $role) }}" title="{{ __('Edit') }}"
                               class="inline-flex items-center justify-center w-9 h-9 bg-[#22d3ee] text-white rounded-lg hover:bg-[#06b6d4] transition shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            @if(!$role->is_system)
                            <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" class="inline" onsubmit="return confirm('{{ __('Are you sure you want to delete this role?') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" title="{{ __('Delete') }}"
                                        class="inline-flex items-center justify-center w-9 h-9 bg-red-400 text-white rounded-lg hover:bg-red-500 transition shadow-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-6 py-12 text-center text-gray-400">{{ __('No roles found. Create your first role.') }}</td></tr>
                @endforelse
            </tbody>
        </table>

        {{-- Pagination Info --}}
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 text-sm text-gray-500">
            <span id="showingInfo">{{ __('Showing') }} 1 {{ __('to') }} {{ min(10, $roles->count()) }} {{ __('of') }} {{ $roles->count() }} {{ __('entries') }}</span>
            <div class="flex items-center gap-1" id="paginationBtns"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let currentPage = 1;
let perPage = 10;
let sortCol = null;
let sortAsc = true;

function filterTable() {
    const search = document.getElementById('roleSearch').value.toLowerCase().trim();
    perPage = parseInt(document.getElementById('perPageSelect').value);
    const rows = document.querySelectorAll('.role-row');

    let visibleRows = [];
    rows.forEach(row => {
        const data = row.getAttribute('data-search') || '';
        const match = !search || data.includes(search);
        row.dataset.filtered = match ? '1' : '0';
        if (match) visibleRows.push(row);
    });

    currentPage = 1;
    paginate(visibleRows);
}

function paginate(rows) {
    if (!rows) {
        rows = [];
        document.querySelectorAll('.role-row[data-filtered="1"], .role-row:not([data-filtered])').forEach(r => {
            if (r.dataset.filtered !== '0') rows.push(r);
        });
    }

    const total = rows.length;
    const pages = Math.ceil(total / perPage) || 1;
    if (currentPage > pages) currentPage = pages;

    const start = (currentPage - 1) * perPage;
    const end = start + perPage;

    // Hide all, show page
    document.querySelectorAll('.role-row').forEach(r => r.style.display = 'none');
    rows.forEach((r, i) => {
        r.style.display = (i >= start && i < end) ? '' : 'none';
        // Renumber
        if (i >= start && i < end) {
            r.querySelector('td').textContent = i + 1;
        }
    });

    // Update info
    document.getElementById('showingInfo').textContent =
        '{{ __("Showing") }} ' + (total ? start + 1 : 0) + ' {{ __("to") }} ' + Math.min(end, total) + ' {{ __("of") }} ' + total + ' {{ __("entries") }}';

    // Build pagination buttons
    const container = document.getElementById('paginationBtns');
    container.innerHTML = '';

    const makeBtn = (label, page, active = false, disabled = false) => {
        const btn = document.createElement('button');
        btn.textContent = label;
        btn.disabled = disabled;
        btn.className = active
            ? 'px-3 py-1.5 rounded-lg text-sm font-semibold bg-[#0ea5e9] text-white'
            : 'px-3 py-1.5 rounded-lg text-sm font-medium ' + (disabled ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-gray-100');
        if (!disabled && !active) btn.onclick = () => { currentPage = page; paginate(); };
        return btn;
    };

    container.appendChild(makeBtn('{{ __("Previous") }}', currentPage - 1, false, currentPage === 1));
    for (let i = 1; i <= pages; i++) {
        container.appendChild(makeBtn(String(i), i, i === currentPage));
    }
    container.appendChild(makeBtn('{{ __("Next") }}', currentPage + 1, false, currentPage === pages));
}

function sortTable(colIdx) {
    if (sortCol === colIdx) { sortAsc = !sortAsc; } else { sortCol = colIdx; sortAsc = true; }

    const body = document.getElementById('rolesBody');
    const rows = Array.from(body.querySelectorAll('.role-row'));

    rows.sort((a, b) => {
        const aText = a.querySelectorAll('td')[colIdx].textContent.trim().toLowerCase();
        const bText = b.querySelectorAll('td')[colIdx].textContent.trim().toLowerCase();
        const aNum = parseFloat(aText), bNum = parseFloat(bText);
        if (!isNaN(aNum) && !isNaN(bNum)) return sortAsc ? aNum - bNum : bNum - aNum;
        return sortAsc ? aText.localeCompare(bText) : bText.localeCompare(aText);
    });

    rows.forEach(r => body.appendChild(r));
    filterTable();
}

// Initialize
document.addEventListener('DOMContentLoaded', () => filterTable());
</script>
@endpush
