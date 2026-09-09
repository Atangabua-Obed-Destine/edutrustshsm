@extends('layouts.admin')
@section('title', __('GCE Exam Series'))

@section('content')
<div class="max-w-7xl mx-auto space-y-6">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">{{ __('GCE Exam Series') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('Ordinary and Advanced Level registration with the GCE Board') }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.gce.subjects.index') }}" class="text-sm font-medium border border-gray-200 hover:bg-gray-50 rounded-lg px-4 py-2">{{ __('Board Subjects') }}</a>
            @can('gce-registration.create')
            <a href="{{ route('admin.gce.sessions.create') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg px-4 py-2">{{ __('New Series') }}</a>
            @endcan
        </div>
    </div>

    @include('admin.gce._flash')

    <div class="bg-white rounded-xl border border-gray-100 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-left">{{ __('Series') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('Level') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('Classes') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('Centre Number') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('Candidates') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($sessions as $series)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900">{{ $series->name }}</div>
                        <div class="text-xs text-gray-400">{{ $series->exam_year }} · {{ $series->academicSession?->name }}</div>
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ $series->level_label }}</td>
                    <td class="px-4 py-3 text-gray-600 text-xs">{{ $series->forms->pluck('name')->join(', ') ?: '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $series->centre_number ?: '—' }}</td>
                    <td class="px-4 py-3 text-center font-medium text-gray-900">{{ $series->candidates_count }}</td>
                    <td class="px-4 py-3 text-center">
                        @php $tone = ['draft' => 'bg-gray-100 text-gray-600', 'open' => 'bg-emerald-50 text-emerald-700', 'closed' => 'bg-amber-50 text-amber-700', 'submitted' => 'bg-indigo-50 text-indigo-700'][$series->status] ?? 'bg-gray-100 text-gray-600'; @endphp
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $tone }}">{{ __(ucfirst($series->status)) }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-3 text-sm">
                            <a href="{{ route('admin.gce.candidates.index', $series) }}" class="text-indigo-600 hover:text-indigo-700 font-medium">{{ __('Candidates') }}</a>
                            @can('gce-registration.edit')
                            <a href="{{ route('admin.gce.sessions.edit', $series) }}" class="text-gray-500 hover:text-gray-700">{{ __('Edit') }}</a>
                            @endcan
                            @can('gce-registration.delete')
                            <form method="POST" action="{{ route('admin.gce.sessions.destroy', $series) }}"
                                  onsubmit="return confirm('{{ __('Delete this exam series?') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-600">{{ __('Delete') }}</button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-400">
                        {{ __('No exam series yet. Create one to start entering candidates.') }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
