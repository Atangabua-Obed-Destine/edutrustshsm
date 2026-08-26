@extends('layouts.admin')

@section('title', __('Add Staff Member'))

@section('content')
<div class="space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('admin.users.index') }}" class="p-2 hover:bg-gray-100 rounded-lg transition">
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Add Staff Member') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('Register a new teacher, administrator, or support staff') }}</p>
        </div>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <ul class="list-disc list-inside text-sm text-red-700">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-6">
            <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-3">{{ __('Personal Information') }}</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('First Name') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" required
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-slate-500 focus:border-slate-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Last Name') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" required
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-slate-500 focus:border-slate-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Other Names') }}</label>
                    <input type="text" name="other_names" value="{{ old('other_names') }}"
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-slate-500 focus:border-slate-500">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Email') }} <span class="text-red-500">*</span></label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-slate-500 focus:border-slate-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Phone') }}</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" placeholder="+237..."
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-slate-500 focus:border-slate-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Gender') }}</label>
                    <select name="gender" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-slate-500 focus:border-slate-500">
                        <option value="">{{ __('— Select —') }}</option>
                        <option value="male" {{ old('gender') === 'male' ? 'selected' : '' }}>{{ __('Male') }}</option>
                        <option value="female" {{ old('gender') === 'female' ? 'selected' : '' }}>{{ __('Female') }}</option>
                    </select>
                </div>
            </div>

            <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-3 pt-2">{{ __('Account Settings') }}</h3>

            {{-- Roles (multi-select) --}}
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700">{{ __('Roles') }} <span class="text-red-500">*</span></label>
                    <span class="text-xs text-gray-500"><span id="rolesSelectedCount">0</span> {{ __('selected') }}</span>
                </div>
                <p class="text-xs text-gray-500 mb-3">{{ __('Select one or more roles. The user will inherit permissions from all assigned roles.') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($roles as $r)
                    <label class="role-card flex items-start gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-[#0ea5e9] hover:bg-sky-50/30 transition has-[:checked]:border-[#0ea5e9] has-[:checked]:bg-sky-50">
                        <input type="checkbox" name="roles[]" value="{{ $r->id }}"
                               class="role-checkbox mt-0.5 w-4 h-4 text-[#0ea5e9] border-gray-300 rounded focus:ring-[#0ea5e9]"
                               {{ in_array($r->id, old('roles', [])) ? 'checked' : '' }}>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-gray-800">{{ $r->display_name }}</span>
                                @if($r->is_system)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">{{ __('SYSTEM') }}</span>
                                @endif
                            </div>
                            @if($r->description)
                                <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ $r->description }}</p>
                            @endif
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Password') }} <span class="text-red-500">*</span></label>
                    <input type="password" name="password" required minlength="8"
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-slate-500 focus:border-slate-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Confirm Password') }} <span class="text-red-500">*</span></label>
                    <input type="password" name="password_confirmation" required
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-slate-500 focus:border-slate-500">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                <a href="{{ route('admin.users.index') }}" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium text-sm transition">{{ __('Cancel') }}</a>
                <button type="submit" class="px-6 py-2.5 bg-slate-800 text-white rounded-lg hover:bg-slate-700 font-medium text-sm transition">
                    {{ __('Create Staff Member') }}
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const cbs = document.querySelectorAll('.role-checkbox');
    const counter = document.getElementById('rolesSelectedCount');
    const update = () => counter.textContent = document.querySelectorAll('.role-checkbox:checked').length;
    cbs.forEach(cb => cb.addEventListener('change', update));
    update();
});
</script>
@endpush
