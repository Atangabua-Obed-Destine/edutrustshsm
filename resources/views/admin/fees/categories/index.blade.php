@extends('layouts.admin')
@section('title', __('Fee Categories'))

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">{{ __('Fee Categories') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('Manage fee types applied to students') }}</p>
        </div>
        <a href="{{ route('admin.fee-categories.create') }}" class="px-4 py-2.5 bg-[#1e293b] text-white rounded-lg hover:bg-[#334155] text-sm font-medium">{{ __('+ New Category') }}</a>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-3 text-left">{{ __('Name') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Code') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Mandatory') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Refundable') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Tuition') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Boarding') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Structures') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Status') }}</th>
                    <th class="px-6 py-3 text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($categories as $cat)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-medium text-gray-900">{{ $cat->name }}</td>
                    <td class="px-6 py-4 text-gray-500">
                        <span class="font-mono bg-gray-100 px-2 py-0.5 rounded text-xs">{{ $cat->code }}</span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($cat->is_mandatory)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">{{ __('Yes') }}</span>
                        @else
                            <span class="text-gray-400">{{ __('No') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($cat->is_refundable)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">{{ __('Yes') }}</span>
                        @else
                            <span class="text-gray-400">{{ __('No') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($cat->is_tuition)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">{{ __('Yes') }}</span>
                        @else
                            <span class="text-gray-400">{{ __('No') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($cat->is_boarding)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-teal-100 text-teal-800">{{ __('Yes') }}</span>
                        @else
                            <span class="text-gray-400">{{ __('No') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center text-gray-600">{{ $cat->fee_structures_count }}</td>
                    <td class="px-6 py-4 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $cat->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                            {{ $cat->is_active ? __('Active') : __('Inactive') }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right space-x-2">
                        <a href="{{ route('admin.fee-categories.edit', $cat) }}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">{{ __('Edit') }}</a>
                        @if($cat->fee_structures_count === 0)
                        <form action="{{ route('admin.fee-categories.destroy', $cat) }}" method="POST" class="inline" onsubmit="return confirm('{{ __('Delete this category?') }}')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 hover:text-red-800 text-xs font-medium">{{ __('Delete') }}</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="px-6 py-12 text-center text-gray-400">{{ __('No fee categories found. Create your first one.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
