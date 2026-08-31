@extends('layouts.admin')
@section('title', __('GCE Board Subjects'))

@section('content')
<div class="max-w-6xl mx-auto space-y-6">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">{{ __('GCE Board Subjects') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('The Board\'s own subject codes, which the entry file is read by') }}</p>
        </div>
        <a href="{{ route('admin.gce.sessions.index') }}" class="text-sm font-medium border border-gray-200 hover:bg-gray-50 rounded-lg px-4 py-2">{{ __('Exam Series') }}</a>
    </div>

    @include('admin.gce._flash')

    <div class="flex gap-2">
        @foreach(['o_level' => __('Ordinary Level'), 'a_level' => __('Advanced Level')] as $value => $label)
        <a href="{{ route('admin.gce.subjects.index', ['level' => $value]) }}"
           class="text-sm font-medium rounded-lg px-4 py-2 {{ $level === $value ? 'bg-indigo-600 text-white' : 'border border-gray-200 hover:bg-gray-50 text-gray-700' }}">
            {{ $label }}
        </a>
        @endforeach
    </div>

    @can('gce-registration.create')
    <form method="POST" action="{{ route('admin.gce.subjects.store') }}" class="bg-white rounded-xl border border-gray-100 p-5">
        @csrf
        <input type="hidden" name="level" value="{{ $level }}">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Board Code') }}</label>
                <input type="text" name="code" required placeholder="0530" class="w-full rounded-lg border-gray-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Subject Name') }}</label>
                <input type="text" name="name" required placeholder="{{ __('Mathematics') }}" class="w-full rounded-lg border-gray-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Maps to School Subject') }}</label>
                <select name="subject_id" class="w-full rounded-lg border-gray-200 text-sm">
                    <option value="">{{ __('Not mapped') }}</option>
                    @foreach($subjects as $subject)
                        <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg px-4 py-2">{{ __('Add Subject') }}</button>
            </div>
        </div>
    </form>
    @endcan

    <div class="bg-white rounded-xl border border-gray-100 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-left">{{ __('Code') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('Subject') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('School Subject') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('Active') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($gceSubjects as $gceSubject)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-gray-900">{{ $gceSubject->code }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ $gceSubject->name }}</td>
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $gceSubject->subject?->name ?? __('Not mapped') }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $gceSubject->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $gceSubject->is_active ? __('Yes') : __('No') }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-3 text-sm">
                            @can('gce-registration.edit')
                            <form method="POST" action="{{ route('admin.gce.subjects.update', $gceSubject) }}">
                                @csrf @method('PUT')
                                <input type="hidden" name="code" value="{{ $gceSubject->code }}">
                                <input type="hidden" name="name" value="{{ $gceSubject->name }}">
                                <input type="hidden" name="level" value="{{ $gceSubject->level }}">
                                <input type="hidden" name="subject_id" value="{{ $gceSubject->subject_id }}">
                                <input type="hidden" name="is_active" value="{{ $gceSubject->is_active ? 0 : 1 }}">
                                <button type="submit" class="text-gray-500 hover:text-gray-700">
                                    {{ $gceSubject->is_active ? __('Deactivate') : __('Activate') }}
                                </button>
                            </form>
                            @endcan
                            @can('gce-registration.delete')
                            <form method="POST" action="{{ route('admin.gce.subjects.destroy', $gceSubject) }}"
                                  onsubmit="return confirm('{{ __('Delete this board subject?') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-600">{{ __('Delete') }}</button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-12 text-center text-sm text-gray-400">
                        {{ __('No board subjects for this level yet. Candidates cannot be entered until there are.') }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
