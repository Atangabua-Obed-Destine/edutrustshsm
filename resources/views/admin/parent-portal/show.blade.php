@extends('layouts.admin')
@section('title', __('Parent Details'))

@section('content')
@php
    $currency = (\App\Models\SchoolSetting::current()->currency ?? 'FCFA');
    $subStatus = [
        'pending' => ['bg-amber-100', 'text-amber-700'],
        'approved' => ['bg-green-100', 'text-green-700'],
        'rejected' => ['bg-red-100', 'text-red-700'],
    ];
@endphp

<div class="max-w-5xl mx-auto" x-data="{ edit: false, setPass: false }">
    @if(session('success'))
        @php $msg = session('success'); $hasLink = \Illuminate\Support\Str::contains($msg, 'http'); @endphp
        <div class="mb-5 bg-green-50 border border-green-200 rounded-lg p-4">
            <p class="text-sm text-green-800">{{ $hasLink ? \Illuminate\Support\Str::before($msg, 'http') : $msg }}</p>
            @if($hasLink)
                @php $link = 'http' . \Illuminate\Support\Str::after($msg, 'http'); @endphp
                <div class="flex items-center gap-2 mt-2">
                    <input type="text" readonly value="{{ $link }}" id="invite-link" class="flex-1 text-xs bg-white border border-green-300 rounded px-3 py-2 font-mono text-green-900">
                    <button onclick="navigator.clipboard.writeText(document.getElementById('invite-link').value); this.textContent='{{ __('Copied!') }}'" class="text-xs bg-green-600 text-white px-3 py-2 rounded hover:bg-green-700">{{ __('Copy') }}</button>
                </div>
            @endif
        </div>
    @endif
    @if(session('error'))
        <div class="mb-5 bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-5 bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-800">
            @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
        </div>
    @endif

    <a href="{{ route('admin.parent-portal.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-4">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        {{ __('Back to Parent Accounts') }}
    </a>

    {{-- Header --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-gray-900 flex items-center justify-center text-teal-300 text-lg font-bold">
                    {{ mb_substr($guardian->display_name, 0, 1) }}
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-800">{{ $guardian->display_name }}</h2>
                    <div class="text-sm text-gray-500 mt-0.5">{{ $guardian->primary_email ?: __('No email on file') }} · {{ $guardian->primary_phone ?: __('no phone') }}</div>
                </div>
            </div>
            <div class="text-right">
                @if($guardian->canAccessPortal())
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-medium">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>{{ __('Portal Active') }}
                    </span>
                    <div class="text-xs text-gray-400 mt-1.5">
                        {{ __('Last login') }}: {{ $guardian->last_login_at ? $guardian->last_login_at->diffForHumans() : __('never') }}
                    </div>
                @elseif($guardian->inviteIsValid())
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-amber-100 text-amber-700 text-xs font-medium">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>{{ __('Invited (awaiting activation)') }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-gray-100 text-gray-500 text-xs font-medium">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>{{ __('No Portal Access') }}
                    </span>
                @endif
            </div>
        </div>

        {{-- Portal access actions --}}
        <div class="flex flex-wrap gap-2 mt-5 pt-5 border-t border-gray-100">
            <form method="POST" action="{{ route('admin.parent-portal.invite', $guardian) }}">
                @csrf
                <button class="text-xs bg-teal-600 text-white px-3 py-2 rounded-lg hover:bg-teal-700">
                    {{ $guardian->inviteIsValid() ? __('Resend Invite Link') : __('Send Invite Link') }}
                </button>
            </form>
            <button type="button" @click="setPass = !setPass" class="text-xs bg-indigo-600 text-white px-3 py-2 rounded-lg hover:bg-indigo-700">{{ __('Set Password Directly') }}</button>
            @if($guardian->canAccessPortal())
                <form method="POST" action="{{ route('admin.parent-portal.reset-password', $guardian) }}" onsubmit="return confirm('{{ __('Reset password? The parent will need a new invite or a new password to log in.') }}')">
                    @csrf
                    <button class="text-xs bg-gray-100 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-200">{{ __('Reset Password') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.parent-portal.disable', $guardian) }}" onsubmit="return confirm('{{ __('Revoke portal access?') }}')">
                    @csrf
                    <button class="text-xs bg-red-50 text-red-600 px-3 py-2 rounded-lg hover:bg-red-100">{{ __('Revoke Access') }}</button>
                </form>
            @endif
        </div>

        {{-- Set password panel --}}
        <div x-show="setPass" x-cloak class="mt-4 bg-indigo-50 border border-indigo-100 rounded-lg p-4">
            <h4 class="text-sm font-semibold text-indigo-900 mb-3">{{ __('Set Portal Password') }}</h4>
            <form method="POST" action="{{ route('admin.parent-portal.set-password', $guardian) }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @csrf
                <input type="email" name="login_email" value="{{ old('login_email', $guardian->primary_email) }}" required placeholder="{{ __('Login email') }}" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
                <input type="password" name="password" required placeholder="{{ __('Password (min 8)') }}" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
                <input type="password" name="password_confirmation" required placeholder="{{ __('Confirm password') }}" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
                <div class="sm:col-span-3">
                    <button class="text-xs bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">{{ __('Save Password & Activate') }}</button>
                    <p class="text-xs text-gray-500 mt-1">{{ __('Use this for parents without email. Share the email & password with them securely.') }}</p>
                </div>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        {{-- Children + activity --}}
        <div class="lg:col-span-3 space-y-6">
            {{-- Children --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-800">{{ __('Children') }}</h3>
                    <span class="text-xs text-gray-400">{{ $guardian->students->count() }} {{ __('linked') }}</span>
                </div>
                @if($guardian->students->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-gray-400">{{ __('No children linked to this parent.') }}</p>
                @else
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-gray-50">
                            @foreach($guardian->students as $child)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3">
                                        <div class="font-medium text-gray-800">{{ $child->full_name }}</div>
                                        <div class="text-xs text-gray-400">{{ $child->student_id }} · {{ $child->currentEnrollment->classSection->name ?? $child->currentEnrollment->classSection->form->name ?? __('Not enrolled') }}</div>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <a href="{{ route('admin.students.show', $child) }}" class="text-xs text-teal-600 font-medium hover:text-teal-700">{{ __('View student') }} →</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Activity: payment submissions --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-800">{{ __('Recent Payment Submissions') }}</h3>
                </div>
                @if($submissions->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-gray-400">{{ __('No payment submissions from this parent.') }}</p>
                @else
                    <table class="w-full text-sm">
                        <thead><tr class="text-left text-xs text-gray-500 uppercase border-b border-gray-100">
                            <th class="px-5 py-2.5">{{ __('Date') }}</th><th class="px-5 py-2.5">{{ __('Child / Fee') }}</th>
                            <th class="px-5 py-2.5 text-right">{{ __('Amount') }}</th><th class="px-5 py-2.5 text-center">{{ __('Status') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($submissions as $s)
                                @php $sc = $subStatus[$s->status] ?? ['bg-gray-100','text-gray-600']; @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3 text-gray-600">{{ $s->created_at->format('d M Y') }}</td>
                                    <td class="px-5 py-3">
                                        <div class="text-gray-800">{{ $s->enrollment->student->first_name ?? '—' }}</div>
                                        <div class="text-xs text-gray-400">{{ $s->studentFee->feeCategory->name ?? __('General') }}</div>
                                    </td>
                                    <td class="px-5 py-3 text-right font-medium text-gray-800">{{ number_format($s->amount, 0) }} {{ $currency }}</td>
                                    <td class="px-5 py-3 text-center"><span class="px-2 py-0.5 rounded-full {{ $sc[0] }} {{ $sc[1] }} text-xs capitalize">{{ $s->status }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        {{-- Contact details (editable) --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-800">{{ __('Contact Details') }}</h3>
                    <button type="button" @click="edit = !edit" class="text-xs text-teal-600 font-medium hover:text-teal-700" x-text="edit ? '{{ __('Cancel') }}' : '{{ __('Edit') }}'"></button>
                </div>

                {{-- Read view --}}
                <div x-show="!edit" class="space-y-3 text-sm">
                    @php
                        $blocks = [
                            __('Guardian') => [$guardian->guardian_name, $guardian->guardian_relationship, $guardian->guardian_phone, $guardian->guardian_email],
                            __('Father') => [$guardian->father_name, null, $guardian->father_phone, $guardian->father_email],
                            __('Mother') => [$guardian->mother_name, null, $guardian->mother_phone, $guardian->mother_email],
                        ];
                    @endphp
                    @foreach($blocks as $label => $b)
                        @if($b[0] || $b[2] || $b[3])
                        <div class="pb-3 border-b border-gray-50 last:border-0">
                            <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">{{ $label }}@if($b[1]) ({{ $b[1] }})@endif</div>
                            <div class="text-gray-800 font-medium">{{ $b[0] ?: '—' }}</div>
                            @if($b[2])<div class="text-gray-500 text-xs mt-0.5">📞 {{ $b[2] }}</div>@endif
                            @if($b[3])<div class="text-gray-500 text-xs">✉️ {{ $b[3] }}</div>@endif
                        </div>
                        @endif
                    @endforeach
                    <div class="pt-1">
                        <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">{{ __('Emergency Contact') }}</div>
                        <div class="text-gray-800 font-medium">{{ $guardian->emergency_contact_name ?: '—' }}</div>
                        @if($guardian->emergency_contact_phone)<div class="text-gray-500 text-xs">📞 {{ $guardian->emergency_contact_phone }}</div>@endif
                    </div>
                </div>

                {{-- Edit form --}}
                <form x-show="edit" x-cloak method="POST" action="{{ route('admin.parent-portal.update', $guardian) }}" class="space-y-3">
                    @csrf @method('PUT')
                    @php
                        $editFields = [
                            'guardian_name' => __('Guardian Name'), 'guardian_relationship' => __('Relationship'),
                            'guardian_phone' => __('Guardian Phone'), 'guardian_email' => __('Guardian Email'),
                            'father_name' => __('Father Name'), 'father_phone' => __('Father Phone'), 'father_email' => __('Father Email'),
                            'mother_name' => __('Mother Name'), 'mother_phone' => __('Mother Phone'), 'mother_email' => __('Mother Email'),
                        ];
                    @endphp
                    @foreach($editFields as $name => $label)
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ $label }}</label>
                            <input type="{{ \Illuminate\Support\Str::endsWith($name, 'email') ? 'email' : 'text' }}" name="{{ $name }}"
                                   value="{{ old($name, $guardian->$name) }}"
                                   class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                        </div>
                    @endforeach
                    <button class="text-xs bg-teal-600 text-white px-4 py-2 rounded-lg hover:bg-teal-700">{{ __('Save Changes') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
<style>[x-cloak]{display:none!important;}</style>
@endsection
