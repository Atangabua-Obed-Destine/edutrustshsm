@extends('layouts.admin')

@section('title', __('Leave Types'))
@section('breadcrumb', __('Human Resources > Leave Types'))

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Leave Types') }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ __('An allowance of 0 or blank means the type is uncapped.') }}
            </p>
        </div>
        <a href="{{ route('admin.leaves.index') }}" class="text-sm text-blue-600 hover:underline">
            {{ __('Back to Staff Leave') }}
        </a>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <ul class="text-sm text-red-800 space-y-1">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-3 text-left">{{ __('Title') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Annual Allowance') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Pay') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Requests') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($types as $type)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 font-medium text-gray-900">{{ $type->title }}</td>
                    <td class="px-6 py-3 text-center text-gray-600">
                        {{ $type->isCapped() ? $type->annual_limit.' '.__('days') : __('Unlimited') }}
                    </td>
                    <td class="px-6 py-3 text-center text-gray-600">{{ $type->is_paid ? __('Paid') : __('Unpaid') }}</td>
                    <td class="px-6 py-3 text-center text-gray-600">{{ $type->leaves_count }}</td>
                    <td class="px-6 py-3 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold
                            {{ $type->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ $type->is_active ? __('Active') : __('Inactive') }}
                        </span>
                    </td>
                    <td class="px-6 py-3 text-right">
                        @can('leave-type.delete')
                        <form method="POST" action="{{ route('admin.leave-types.destroy', $type) }}" class="inline"
                              onsubmit="return confirm('{{ __('Delete this leave type?') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">{{ __('No leave types yet.') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @can('leave-type.create')
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ __('Add a leave type') }}</h3>
        <form method="POST" action="{{ route('admin.leave-types.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Title') }}</label>
                    <input type="text" name="title" value="{{ old('title') }}" required maxlength="100"
                           placeholder="{{ __('e.g. Annual Leave') }}"
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">
                        {{ __('Annual Allowance') }} <span class="text-gray-400">({{ __('blank = unlimited') }})</span>
                    </label>
                    <input type="number" name="annual_limit" value="{{ old('annual_limit') }}" min="0" max="365"
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div class="flex items-end gap-4">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="hidden" name="is_paid" value="0">
                        <input type="checkbox" name="is_paid" value="1" checked class="rounded border-gray-300">
                        {{ __('Paid') }}
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300">
                        {{ __('Active') }}
                    </label>
                </div>
            </div>
            <button type="submit" class="px-4 py-2.5 bg-slate-800 text-white rounded-lg text-sm font-medium hover:bg-slate-700">
                {{ __('Add Type') }}
            </button>
        </form>
    </div>
    @endcan
</div>
@endsection
