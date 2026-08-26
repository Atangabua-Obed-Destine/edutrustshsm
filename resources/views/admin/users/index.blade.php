@extends('layouts.admin')

@section('title', __('Staff Management'))

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Staff Management') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('Manage teachers, administrators and support staff') }}</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="inline-flex items-center px-4 py-2.5 bg-slate-800 text-white rounded-lg hover:bg-slate-700 font-medium text-sm transition">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            {{ __('Add Staff') }}
        </a>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
        <div class="flex items-center">
            <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-green-800 text-sm font-medium">{{ session('success') }}</span>
        </div>
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="flex items-center">
            <svg class="w-5 h-5 text-red-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-red-800 text-sm font-medium">{{ session('error') }}</span>
        </div>
    </div>
    @endif

    {{-- Role Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @php $roles = ['super_admin' => 'Super Admin', 'admin' => 'Admin', 'teacher' => 'Teacher', 'staff' => 'Staff']; @endphp
        @foreach($roles as $rKey => $rLabel)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $rLabel }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $roleCounts[$rKey] ?? 0 }}</p>
        </div>
        @endforeach
    </div>

    {{-- Search & Filter --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <form method="GET" action="{{ route('admin.users.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div class="sm:col-span-2">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by name or email...') }}"
                       class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-slate-500 focus:border-slate-500 text-sm">
            </div>
            <div>
                <select name="role" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-slate-500 focus:border-slate-500 text-sm">
                    <option value="">{{ __('All Roles') }}</option>
                    @foreach($roles as $rKey => $rLabel)
                    <option value="{{ $rKey }}" {{ request('role') === $rKey ? 'selected' : '' }}>{{ $rLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-700 text-sm font-medium transition">{{ __('Filter') }}</button>
                <a href="{{ route('admin.users.index') }}" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 text-sm font-medium transition">{{ __('Reset') }}</a>
            </div>
        </form>
    </div>

    {{-- Users Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">{{ __('Staff Member') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">{{ __('Email') }}</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">{{ __('Role') }}</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">{{ __('Status') }}</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">{{ __('Last Login') }}</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($users as $u)
                    <tr class="hover:bg-gray-50 transition {{ !$u->is_active ? 'opacity-60' : '' }}">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full {{ $u->is_active ? 'bg-slate-800' : 'bg-gray-400' }} text-white flex items-center justify-center text-sm font-bold shrink-0">
                                    {{ strtoupper(substr($u->first_name, 0, 1) . substr($u->last_name, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">{{ $u->last_name }} {{ $u->first_name }}</div>
                                    @if($u->phone)
                                    <div class="text-xs text-gray-400">{{ $u->phone }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $u->email }}</td>
                        <td class="px-6 py-4 text-center">
                            @php
                                $roleColors = [
                                    'super_admin' => 'bg-purple-100 text-purple-700',
                                    'admin' => 'bg-blue-100 text-blue-700',
                                    'teacher' => 'bg-green-100 text-green-700',
                                    'staff' => 'bg-gray-100 text-gray-700',
                                ];
                            @endphp
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded-full {{ $roleColors[$u->role] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ ucfirst(str_replace('_', ' ', $u->role)) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="inline-flex items-center gap-1 text-xs font-medium {{ $u->is_active ? 'text-green-600' : 'text-red-500' }}">
                                <span class="w-2 h-2 rounded-full {{ $u->is_active ? 'bg-green-500' : 'bg-red-400' }}"></span>
                                {{ $u->is_active ? __('Active') : __('Inactive') }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center text-xs text-gray-400">
                            {{ $u->last_login_at ? $u->last_login_at->diffForHumans() : __('Never') }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <a href="{{ route('admin.users.edit', $u) }}" class="p-1.5 text-gray-400 hover:text-slate-700 hover:bg-gray-100 rounded-lg transition" title="{{ __('Edit') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                @if($u->id !== auth()->id() && $u->role !== 'super_admin')
                                <form method="POST" action="{{ route('admin.users.toggle-active', $u) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="p-1.5 {{ $u->is_active ? 'text-gray-400 hover:text-red-600 hover:bg-red-50' : 'text-gray-400 hover:text-green-600 hover:bg-green-50' }} rounded-lg transition" title="{{ $u->is_active ? __('Deactivate') : __('Activate') }}">
                                        @if($u->is_active)
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                        @else
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        @endif
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <p class="text-gray-500">{{ __('No staff members found.') }}</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $users->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
