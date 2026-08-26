@extends('layouts.admin')
@section('title', __('Parent Accounts'))

@section('content')
<div class="max-w-6xl mx-auto">

    {{-- Show generated invite link (flashed in success message containing http) --}}
    @if(session('success') && \Illuminate\Support\Str::contains(session('success'), 'http'))
        @php
            $fullLink = mb_substr(session('success'), mb_strpos(session('success'), 'http'));
        @endphp
        <div class="mb-5 bg-teal-50 border border-teal-200 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-teal-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101"/></svg>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-teal-800">{{ __('Invitation link generated') }}</p>
                    <p class="text-xs text-teal-700 mt-1">{{ __('Copy this link and send it to the parent (SMS / WhatsApp / email). It expires in 48 hours.') }}</p>
                    <div class="flex items-center gap-2 mt-2">
                        <input type="text" readonly value="{{ $fullLink }}" id="invite-link-input"
                               class="flex-1 text-xs bg-white border border-teal-300 rounded px-3 py-2 text-teal-900 font-mono">
                        <button onclick="navigator.clipboard.writeText(document.getElementById('invite-link-input').value); this.textContent='{{ __('Copied!') }}'"
                                class="text-xs bg-teal-600 text-white px-3 py-2 rounded hover:bg-teal-700 whitespace-nowrap">{{ __('Copy') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @elseif(session('success'))
        <div class="mb-5 bg-green-50 border border-green-200 rounded-lg p-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="mb-5 bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        {{-- Header --}}
        <div class="px-6 py-5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-gray-800">{{ __('Parent / Guardian Portal Access') }}</h3>
                <p class="text-sm text-gray-500 mt-0.5">{{ __('Enable login access so parents can follow their children online.') }}</p>
            </div>
            <form method="GET" class="flex items-center gap-2">
                <input type="text" name="q" value="{{ $search }}" placeholder="{{ __('Search name or email...') }}"
                       class="text-sm border border-gray-300 rounded-lg px-3 py-2 w-full sm:w-64 focus:ring-2 focus:ring-teal-500 outline-none">
                <button type="submit" class="text-sm bg-gray-800 text-white px-3 py-2 rounded-lg hover:bg-gray-900">{{ __('Search') }}</button>
            </form>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-500 uppercase tracking-wide border-b border-gray-100">
                        <th class="px-6 py-3 font-medium">{{ __('Guardian') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Contact') }}</th>
                        <th class="px-6 py-3 font-medium text-center">{{ __('Children') }}</th>
                        <th class="px-6 py-3 font-medium text-center">{{ __('Status') }}</th>
                        <th class="px-6 py-3 font-medium text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($guardians as $guardian)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-800">{{ $guardian->display_name }}</div>
                                @if($guardian->guardian_relationship)
                                    <div class="text-xs text-gray-400">{{ ucfirst($guardian->guardian_relationship) }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-gray-600">
                                <div>{{ $guardian->primary_email ?: '—' }}</div>
                                <div class="text-xs text-gray-400">{{ $guardian->primary_phone ?: '' }}</div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center justify-center min-w-[24px] h-6 px-2 rounded-full bg-gray-100 text-gray-700 text-xs font-semibold">{{ $guardian->students_count }}</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($guardian->canAccessPortal())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-green-100 text-green-700 text-xs font-medium">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>{{ __('Active') }}
                                    </span>
                                @elseif($guardian->inviteIsValid())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 text-xs font-medium">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>{{ __('Invited') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gray-100 text-gray-500 text-xs font-medium">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>{{ __('No Access') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.parent-portal.show', $guardian) }}"
                                       class="inline-flex items-center gap-1 text-xs bg-gray-800 text-white px-3 py-1.5 rounded-lg hover:bg-gray-900">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        {{ __('Manage') }}
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-400">
                                <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <p class="text-sm">{{ $search ? __('No guardians match your search.') : __('No guardians on file yet.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($guardians->hasPages())
            <div class="px-6 py-4 border-t border-gray-100">
                {{ $guardians->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
