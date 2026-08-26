@extends('layouts.admin')

@section('title', __('Branches'))
@section('breadcrumb', __('Branches'))

@section('content')
<div class="space-y-4" x-data="{ editing: null, assigning: null }">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-700">{{ __('Branches / Campuses') }}</h3>
    </div>

    {{-- Create --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h4 class="text-sm font-semibold text-gray-600 mb-3">{{ __('Add Branch') }}</h4>
        <form method="POST" action="{{ route('admin.branches.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            @csrf
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Name') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Code') }}</label>
                <input type="text" name="code" value="{{ old('code') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm uppercase" required>
                @error('code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-lg text-sm font-medium">+ {{ __('Create Branch') }}</button>
        </form>
        <p class="text-xs text-gray-400 mt-2">{{ __('A new branch gets its own settings (name, prefixes) and, as modules roll out, its own academic & financial data.') }}</p>
    </div>

    {{-- List --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Name') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Code') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Users') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach($branches as $branch)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $branch->name }}</td>
                    <td class="px-4 py-3 text-sm font-mono text-gray-600">{{ $branch->code }}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-600">{{ $branch->users_count }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $branch->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $branch->is_active ? __('Active') : __('Inactive') }}</span>
                    </td>
                    <td class="px-4 py-3 text-right space-x-3">
                        <button type="button" class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                                @click="editing = (editing === {{ $branch->id }} ? null : {{ $branch->id }}); assigning = null">{{ __('Edit') }}</button>
                        <button type="button" class="text-teal-700 hover:text-teal-900 text-sm font-medium"
                                @click="assigning = (assigning === {{ $branch->id }} ? null : {{ $branch->id }}); editing = null">{{ __('Manage Users') }}</button>
                    </td>
                </tr>
                {{-- Edit row --}}
                <tr x-show="editing === {{ $branch->id }}" x-cloak class="bg-blue-50/40">
                    <td colspan="5" class="px-4 py-4">
                        <form method="POST" action="{{ route('admin.branches.update', $branch) }}" class="flex flex-wrap items-end gap-3">
                            @csrf @method('PUT')
                            <div><label class="block text-xs text-gray-500 mb-1">{{ __('Name') }}</label><input type="text" name="name" value="{{ $branch->name }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm" required></div>
                            <div><label class="block text-xs text-gray-500 mb-1">{{ __('Code') }}</label><input type="text" name="code" value="{{ $branch->code }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm uppercase" required></div>
                            <div><label class="block text-xs text-gray-500 mb-1">{{ __('Status') }}</label>
                                <select name="is_active" style="appearance:auto;-webkit-appearance:menulist;" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                    <option value="1" {{ $branch->is_active ? 'selected' : '' }}>{{ __('Active') }}</option>
                                    <option value="0" {{ !$branch->is_active ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                                </select>
                            </div>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Save') }}</button>
                        </form>
                    </td>
                </tr>
                {{-- Assign users row --}}
                <tr x-show="assigning === {{ $branch->id }}" x-cloak class="bg-teal-50/40">
                    <td colspan="5" class="px-4 py-4">
                        <form method="POST" action="{{ route('admin.branches.users', $branch) }}">
                            @csrf
                            @php($assigned = $branch->users->pluck('id')->all())
                            <p class="text-xs text-gray-500 mb-2">{{ __('Select staff who can access this branch (super admins always can).') }}</p>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 max-h-48 overflow-y-auto mb-3">
                                @foreach($staff as $member)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="user_ids[]" value="{{ $member->id }}" {{ in_array($member->id, $assigned) ? 'checked' : '' }}>
                                    {{ $member->first_name }} {{ $member->last_name }} <span class="text-xs text-gray-400">({{ $member->role }})</span>
                                </label>
                                @endforeach
                            </div>
                            <button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Save Access') }}</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
