@extends('layouts.admin')

@section('title', __('Class Sections'))
@section('breadcrumb', __('Academic > Class Sections'))

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-700">{{ __('Class Sections') }}</h3>
            <p class="text-sm text-gray-500">{{ __('Manage class sections for each form') }}</p>
        </div>
        <a href="{{ route('admin.class-sections.create') }}" class="bg-[#1e293b] hover:bg-[#334155] text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            {{ __('+ New Class Section') }}
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Name') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Form') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Class Teacher') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Room') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Capacity') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($classSections as $cs)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $cs->name }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $cs->form->name }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $cs->classTeacher?->full_name ?? '—' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $cs->room?->name ?? '—' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $cs->max_students }}</td>
                    <td class="px-6 py-4 text-right space-x-2">
                        <a href="{{ route('admin.class-sections.edit', $cs) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">{{ __('Edit') }}</a>
                        <form method="POST" action="{{ route('admin.class-sections.destroy', $cs) }}" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium" onclick="return confirm('{{ __('Delete this class section?') }}')">{{ __('Delete') }}</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">{{ __('No class sections found. Add your first class section.') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
