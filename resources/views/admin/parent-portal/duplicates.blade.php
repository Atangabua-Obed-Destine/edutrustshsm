@extends('layouts.admin')
@section('title', __('Duplicate Parents'))
@section('breadcrumb', __('Parent Portal > Duplicates'))

@section('content')
<div class="space-y-5">

    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">{{ __('Parents recorded more than once') }}</h2>
            <p class="text-sm text-gray-500 mt-0.5 max-w-3xl">
                {{ __('A parent enrolling a second child used to be recorded afresh, because matching only looked at email addresses and the registration form never asked for one. These look like the same person.') }}
            </p>
        </div>
        <a href="{{ route('admin.parent-portal.index') }}"
           class="px-3 py-2 rounded-lg bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium transition">← {{ __('Back') }}</a>
    </div>

    @if($groups->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center">
            <p class="text-sm text-emerald-600 font-medium">{{ __('No duplicates found.') }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ __('New registrations are now matched on phone number as well as email, so a parent enrolling another child is recognised.') }}</p>
        </div>
    @else
        @foreach($groups as $group)
            <form method="POST" action="{{ route('admin.parent-portal.merge') }}"
                  class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden"
                  onsubmit="return confirm(@json(__('Merge these records into the one you selected to keep? This cannot be undone.')))">
                @csrf

                <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-800">
                            {{ $group['guardians']->first()->display_name }}
                        </span>
                        <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-xs">{{ $group['reason'] }}</span>
                        <span class="text-xs text-gray-400">{{ __(':count records', ['count' => $group['guardians']->count()]) }}</span>
                    </div>
                    <button type="submit" class="bg-[#1e293b] hover:bg-[#334155] text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                        {{ __('Merge into the record I keep') }}
                    </button>
                </div>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 border-b border-gray-200">
                            <th class="px-5 py-2.5 w-24">{{ __('Keep') }}</th>
                            <th class="px-5 py-2.5">{{ __('Record') }}</th>
                            <th class="px-5 py-2.5">{{ __('Contact') }}</th>
                            <th class="px-5 py-2.5">{{ __('Children') }}</th>
                            <th class="px-5 py-2.5">{{ __('Portal') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($group['guardians'] as $guardian)
                            @php($isSuggested = $group['suggested']->id === $guardian->id)
                            <tr class="{{ $isSuggested ? 'bg-teal-50/40' : '' }}">
                                <td class="px-5 py-3">
                                    {{-- Every record in the group is submitted; the one picked
                                         to keep is excluded server-side, so there is no second
                                         control to keep in step with this one. --}}
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="keeper_id" value="{{ $guardian->id }}"
                                               @checked($isSuggested) required class="text-teal-600">
                                        @if($isSuggested)
                                            <span class="text-[0.65rem] text-teal-700 font-semibold">{{ __('Suggested') }}</span>
                                        @endif
                                    </label>
                                    <input type="hidden" name="merge_ids[]" value="{{ $guardian->id }}">
                                </td>
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.parent-portal.show', $guardian) }}" class="font-medium text-gray-800 hover:text-teal-700">
                                        {{ $guardian->display_name }}
                                    </a>
                                    <div class="text-xs text-gray-400">#{{ $guardian->id }}</div>
                                </td>
                                <td class="px-5 py-3 text-gray-600">
                                    <div>{{ $guardian->primary_email ?: '—' }}</div>
                                    <div class="text-xs text-gray-400">{{ $guardian->primary_phone ?: '' }}</div>
                                </td>
                                <td class="px-5 py-3">
                                    @forelse($guardian->students as $child)
                                        <div class="text-xs text-gray-700">{{ $child->first_name }} {{ $child->last_name }}</div>
                                    @empty
                                        <span class="text-xs text-gray-400">{{ __('None') }}</span>
                                    @endforelse
                                </td>
                                <td class="px-5 py-3">
                                    @if($guardian->canAccessPortal())
                                        <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-xs">{{ __('Active') }}</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-xs">{{ __('No Access') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="px-5 py-3 bg-gray-50/60 border-t border-gray-100">
                    <p class="text-xs text-gray-500">
                        {{ __('The record you keep gains every child and payment from the others, and any contact detail it was missing. Its own details are never overwritten. The other records are then deleted.') }}
                    </p>
                </div>
            </form>
        @endforeach
    @endif
</div>
@endsection
