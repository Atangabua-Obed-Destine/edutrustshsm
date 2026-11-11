@extends('layouts.admin')

@section('title', __('EdutrustPay Reporting'))

@section('content')

<div class="mb-6">
    <h1 class="text-xl font-semibold text-gray-900">{{ __('EdutrustPay Reporting') }}</h1>
    <p class="mt-1 max-w-3xl text-sm text-gray-600">
        {{ __('This school PUSHES a signed summary of each month, and its full daybook, to its body\'s console. The daybook includes the names of the people on each line, so pupils\' and suppliers\' names leave this system. Nothing reaches in here: outbound only, no inbound endpoint, no access to this database from that side.') }}
    </p>
</div>

{{--
    Is it sending?

    Reports go out through scheduled jobs, and the scheduler only runs if this
    computer has been told to start it every minute. If nobody did, nothing
    fails and nothing is logged: the school just goes quiet on the console. This
    says which it is, lets someone send everything now, and shows what each step
    said. Each branch's own deliveries are shown on its card below.
--}}
@php $run = session('etp_run'); @endphp
<div id="sending" class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
    <h2 class="text-sm font-semibold text-gray-900">{{ __('Is it sending?') }}</h2>
    <p class="mt-1 text-xs text-gray-500">{{ __('Reports go to the console on their own every night, if the scheduler is running on this computer. You can also send everything now.') }}</p>

    @if ($run)
        <div @class([
            'mt-3 rounded border px-3 py-2 text-sm',
            'border-emerald-200 bg-emerald-50 text-emerald-900' => $run['ok'],
            'border-rose-200 bg-rose-50 text-rose-900' => ! $run['ok'],
        ])>
            <p class="font-semibold">{{ $run['ok'] ? __('Sent') : __('Something is wrong') }}</p>
            <div class="mt-1 space-y-1">
                @foreach ($run['steps'] as $step)
                    <details>
                        <summary class="cursor-pointer">
                            @if ($step['ok'])
                                <span class="text-emerald-700">&#10003;</span>
                            @else
                                <span class="text-rose-700">&#10007;</span>
                            @endif
                            {{ $step['label'] }}
                        </summary>
                        <pre class="mt-1 max-h-64 overflow-auto whitespace-pre-wrap rounded border border-gray-200 bg-white p-2 text-xs text-gray-700">{{ $step['output'] !== '' ? $step['output'] : __('(nothing to say)') }}</pre>
                    </details>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
        <span class="font-medium text-gray-700">{{ __('Automatic sending (scheduler)') }}</span>
        @if ($scheduler === 'running')
            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800">{{ __('Running') }}</span>
            <span class="text-xs text-gray-500">{{ __('last ran :when', ['when' => $schedulerSeen->diffForHumans()]) }}</span>
        @elseif ($scheduler === 'stopped')
            <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-medium text-rose-800">{{ __('Stopped') }}</span>
            <span class="text-xs text-gray-500">{{ __('last ran :when. Nothing goes out on its own until it runs again.', ['when' => $schedulerSeen->diffForHumans()]) }}</span>
        @else
            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">{{ __('Not set up on this computer') }}</span>
            <span class="text-xs text-gray-500">{{ __('Reports only go out when someone presses “Send reports now”.') }}</span>
        @endif
    </div>

    @if ($scheduler !== 'running')
        <details class="mt-3 text-sm" @if ($scheduler === 'never') open @endif>
            <summary class="cursor-pointer font-medium text-gray-800">{{ __('How to start the scheduler on this computer (once)') }}</summary>
            <div class="mt-2 text-xs text-gray-600">
                @if ($schedulerSetup['windows'])
                    <p>{{ __('Open “Command Prompt” as Administrator (right-click, Run as administrator), paste this line and press Enter:') }}</p>
                @else
                    <p>{{ __('Add this as a cron job that runs every minute. On your own server: run “sudo crontab -u www-data -e”, paste the whole line at the bottom and save. On shared hosting (Hostinger, cPanel): open Cron Jobs in the hosting panel, choose every minute, and paste the command, which is everything after the five stars.') }}</p>
                @endif
                <pre class="mt-1 select-all whitespace-pre-wrap rounded border border-gray-200 bg-gray-50 p-2 font-mono text-xs text-gray-800">{{ $schedulerSetup['command'] }}</pre>
                <p class="mt-1">{{ __('It starts the scheduler every minute, and the scheduler decides what is due: reports at 03:00, delivery every hour, a check-in at 05:30. Come back here a minute later: this page should say “Running”.') }}</p>
            </div>
        </details>
    @endif

    @can('edutrustpay-reporting.test')
        <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4">
            @if ($reporting)
                <form method="POST" action="{{ route('admin.edutrustpay.send-now') }}"
                      onsubmit="var b=this.querySelector('button'); b.disabled=true; b.innerText=@js(__('Sending… this can take a minute'));">
                    @csrf
                    <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60">
                        {{ __('Send reports now') }}
                    </button>
                </form>
                <span class="max-w-2xl text-xs text-gray-500">{{ __('Runs the nightly jobs now for every branch that reports: builds the last 3 months’ reports, daybooks and budget sheets, sends a check-in and delivers everything waiting. Months that have not changed are not sent again.') }}</span>
            @else
                <button type="button" disabled class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white opacity-50">{{ __('Send reports now') }}</button>
                <span class="text-xs text-gray-500">{{ __('Fill in a branch’s connection below, tick “Send reports for this branch” and save first.') }}</span>
            @endif
        </div>
    @endcan
</div>

{{--
    What this school can report, stated before the credentials.

    Somebody looking at the body's console will see "not yet reporting" against
    several figures and should be able to find out why from this end, without
    having to ask.
--}}
<div class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
    <h2 class="text-sm font-semibold text-gray-900">{{ __('What this school reports') }}</h2>

    <div class="mt-3 flex flex-wrap gap-2">
        @foreach ($capabilities as $capability)
            <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
                {{ str_replace('_', ' ', $capability) }}
            </span>
        @endforeach

        @foreach ($notDeclared as $capability)
            <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-500" title="{{ __('Not reported yet — shows on the console as “not yet reporting”, never as zero.') }}">
                {{ str_replace('_', ' ', $capability) }}
            </span>
        @endforeach
    </div>

    <p class="mt-3 text-xs text-gray-500">
        {{ __('Greyed items are not reported. On the console they show as “not yet reporting” rather than as zero — a missing figure and a zero figure are different facts. This list is set in code, not here, because it describes what this system can actually produce.') }}
    </p>

    @if (in_array('ledger', $notDeclared, true))
        <p class="mt-2 text-xs text-amber-700">
            {{ __('Ledger and integrity are not reported because no journal entries have been posted in this system yet. That is the largest gap and the one worth closing first.') }}
        </p>
    @endif
</div>

@foreach ($branches as $branch)
    @php
        $setting = $settings->get($branch->id);
        $current = $resolved[$branch->id] ?? null;
    @endphp

    <div class="mb-6 rounded-lg border border-gray-200 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">{{ $branch->name }}</h2>
                <p class="text-xs text-gray-500">
                    {{ __('Each branch is a separate institution on the platform, with its own credentials.') }}
                </p>
            </div>

            @if ($current)
                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800">
                    {{ $current['enabled'] ? __('reporting') : __('configured, switched off') }}
                    <span class="opacity-70">· {{ $current['source'] === 'env' ? __('from .env') : __('from this screen') }}</span>
                </span>
            @else
                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">{{ __('not configured') }}</span>
            @endif
        </div>

        <div class="p-4">
            @if ($current && $current['source'] === 'env')
                {{-- Explain the precedence before somebody saves and wonders why
                     nothing changed. --}}
                <div class="mb-4 rounded border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-900">
                    {{ __('This branch is currently using values from the .env file. Anything you save here takes precedence from the moment you save it.') }}
                </div>
            @endif

            {{-- What this branch has sent, and what the console said. --}}
            @php $send = $sending[$branch->id]; @endphp
            @if ($current || $send['recent']->isNotEmpty())
                <dl class="mb-4 grid gap-3 rounded border border-gray-200 bg-gray-50 p-3 text-xs sm:grid-cols-3">
                    <div>
                        <dt class="text-gray-500">{{ __('Last received by the console') }}</dt>
                        <dd class="mt-0.5 font-medium text-gray-900">
                            @if ($send['lastDelivered'])
                                {{ \Illuminate\Support\Carbon::parse($send['lastDelivered'])->diffForHumans() }}
                                <span class="font-normal text-gray-500">({{ \Illuminate\Support\Carbon::parse($send['lastDelivered'])->format('d M Y, H:i') }})</span>
                            @else
                                <span class="text-rose-700">{{ __('Never') }}</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Waiting to go') }}</dt>
                        <dd class="mt-0.5 font-medium {{ $send['waiting'] ? 'text-amber-700' : 'text-gray-900' }}">
                            {{ $send['waiting'] ?: __('Nothing') }}
                            @if ($send['waiting'])
                                <span class="block font-normal text-gray-500">{{ __('kept here and tried again on the next delivery, so nothing is lost') }}</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Refused by the console') }}</dt>
                        <dd class="mt-0.5 font-medium {{ $send['problems']->isNotEmpty() ? 'text-rose-700' : 'text-gray-900' }}">
                            {{ $send['problems']->count() ?: __('Nothing') }}
                            @if ($send['problems']->isNotEmpty())
                                <span class="block font-normal">{{ \App\Http\Controllers\Admin\EdutrustPayController::explain($send['problems']->first()) }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                @if ($send['recent']->isNotEmpty())
                    <details class="mb-4 text-xs">
                        <summary class="cursor-pointer font-medium text-gray-700">{{ __('Last items sent') }}</summary>
                        <div class="mt-2 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 border border-gray-200">
                                <thead class="bg-gray-50 text-left text-gray-600">
                                    <tr>
                                        <th class="px-2 py-1.5 font-medium">{{ __('What') }}</th>
                                        <th class="px-2 py-1.5 font-medium">{{ __('Month') }}</th>
                                        <th class="px-2 py-1.5 font-medium">{{ __('State') }}</th>
                                        <th class="px-2 py-1.5 font-medium">{{ __('When') }}</th>
                                        <th class="px-2 py-1.5 font-medium">{{ __('Console’s answer') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($send['recent'] as $item)
                                        <tr>
                                            <td class="px-2 py-1.5">
                                                {{ \App\Http\Controllers\Admin\EdutrustPayController::kindLabel($item->kind) }}
                                                @if ($item->sequence > 1 && $item->kind !== 'heartbeat')
                                                    <span class="text-gray-400">{{ __('version :n', ['n' => $item->sequence]) }}</span>
                                                @endif
                                            </td>
                                            <td class="px-2 py-1.5">{{ $item->period ? \Illuminate\Support\Carbon::parse($item->period.'-01')->translatedFormat('F Y') : '—' }}</td>
                                            <td class="px-2 py-1.5">
                                                @if ($item->status === 'delivered')
                                                    <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-emerald-800">{{ __('received') }}</span>
                                                @elseif ($item->status === 'pending')
                                                    <span class="rounded bg-amber-100 px-1.5 py-0.5 text-amber-800">{{ __('waiting') }}</span>
                                                @else
                                                    <span class="rounded bg-rose-100 px-1.5 py-0.5 text-rose-800">{{ __('refused') }}</span>
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap px-2 py-1.5">{{ ($item->delivered_at ?? $item->updated_at)?->diffForHumans() }}</td>
                                            <td class="px-2 py-1.5 text-gray-500">{{ $item->status === 'delivered' ? '' : \App\Http\Controllers\Admin\EdutrustPayController::explain($item) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif
            @endif

            @if ($setting?->last_tested_at)
                <div @class([
                    'mb-4 rounded border px-3 py-2 text-xs',
                    'border-emerald-200 bg-emerald-50 text-emerald-900' => $setting->last_test_ok,
                    'border-rose-200 bg-rose-50 text-rose-900' => ! $setting->last_test_ok,
                ])>
                    <span class="font-semibold">
                        {{ $setting->last_test_ok ? __('Last test passed') : __('Last test failed') }}
                        · {{ $setting->last_tested_at->diffForHumans() }}
                    </span>
                    <p class="mt-0.5">{{ $setting->last_test_message }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.edutrustpay.update', $branch) }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label for="endpoint-{{ $branch->id }}" class="block text-sm font-medium text-gray-700">
                            {{ __('Console address') }}
                        </label>
                        <input type="url" name="endpoint" id="endpoint-{{ $branch->id }}"
                               value="{{ old('endpoint', $setting->endpoint ?? config('edutrustpay.endpoint')) }}"
                               placeholder="https://"
                               class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <p class="mt-1 text-xs text-gray-500">{{ __('Given to you by the body. Reports are sent here; nothing is ever received from it.') }}</p>
                        @error('endpoint')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="ref-{{ $branch->id }}" class="block text-sm font-medium text-gray-700">
                            {{ __('Institution reference') }}
                        </label>
                        <input type="text" name="institution_ref" id="ref-{{ $branch->id }}"
                               value="{{ old('institution_ref', $setting->institution_ref ?? '') }}"
                               placeholder="00000000-0000-0000-0000-000000000000"
                               class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm shadow-sm">
                        <p class="mt-1 text-xs text-gray-500">{{ __('Identifies this school on the platform.') }}</p>
                        @error('institution_ref')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="key-{{ $branch->id }}" class="block text-sm font-medium text-gray-700">
                            {{ __('Key id') }}
                        </label>
                        <input type="text" name="key_id" id="key-{{ $branch->id }}"
                               value="{{ old('key_id', $setting->key_id ?? '') }}"
                               placeholder="etp_..."
                               class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm shadow-sm">
                        @error('key_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="secret-{{ $branch->id }}" class="block text-sm font-medium text-gray-700">
                            {{ __('Secret') }}
                        </label>
                        <input type="password" name="secret" id="secret-{{ $branch->id }}"
                               autocomplete="new-password"
                               placeholder="{{ $setting?->hasSecret() ? __('Stored — leave blank to keep it') : __('Paste the secret you were given') }}"
                               class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm shadow-sm">

                        {{-- A stored secret is never displayed. Blank means keep,
                             not clear — otherwise correcting a typo in the
                             endpoint would silently wipe a working credential. --}}
                        <p class="mt-1 text-xs text-gray-500">
                            @if ($setting?->hasSecret())
                                {{ __('A secret is stored (:hint). It is never shown again — leave this blank unless you are replacing it.', ['hint' => $setting->secretHint()]) }}
                            @else
                                {{ __('The operator shows this once when the credential is issued. It cannot be recovered from the console afterwards, only replaced.') }}
                            @endif
                        </p>
                        @error('secret')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" value="1"
                           @checked(old('enabled', $setting->enabled ?? false))
                           class="rounded border-gray-300">
                    {{ __('Send reports for this branch') }}
                </label>

                <div class="flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4">
                    @can('edutrustpay-reporting.edit')
                        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                            {{ __('Save') }}
                        </button>
                    @else
                        <p class="text-xs text-gray-500">{{ __('You can view these settings but not change them.') }}</p>
                    @endcan
                </div>
            </form>

            @can('edutrustpay-reporting.test')
                <form method="POST" action="{{ route('admin.edutrustpay.test', $branch) }}" class="mt-3 border-t border-gray-100 pt-3">
                    @csrf
                    <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium hover:bg-gray-50">
                        {{ __('Test connection') }}
                    </button>
                    <span class="ml-2 text-xs text-gray-500">
                        {{ __('Sends a signed heartbeat. Proves the credentials still work — the most likely failure is a key rotated on the platform and never updated here.') }}
                    </span>
                </form>
            @endcan
        </div>
    </div>
@endforeach

<p class="text-xs text-gray-500">
    {{ __('Contract version :version. Credentials are issued by the body\'s operator and pasted here — this school cannot create its own, or the platform would have to trust whatever it was told.', ['version' => $contractVersion]) }}
</p>

@endsection
