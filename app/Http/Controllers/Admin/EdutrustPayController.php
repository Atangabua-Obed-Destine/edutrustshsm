<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\EdutrustPayOutbox;
use App\Models\EdutrustPaySetting;
use App\Services\EdutrustPay\SchedulerPulse;
use App\Services\EdutrustPay\SettingsResolver;
use EdutrustPay\Contract\Capability;
use EdutrustPay\Contract\ContractVersion;
use EdutrustPay\Contract\Signer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

/**
 * The EdutrustPay connection settings screen.
 *
 * WHAT THIS SCREEN IS FOR: a bursar receives three values from the body's
 * operator — an institution reference, a key id and a secret — and needs to put
 * them somewhere. Before this existed, that meant editing .env and running an
 * artisan command, which is not something to ask of the person who actually
 * holds the credentials.
 *
 * WHAT IT DELIBERATELY DOES NOT DO:
 *
 *   - It never writes .env. That would need the web user to have write access
 *     to a file holding every secret the application owns.
 *   - It never displays a saved secret. Leaving it blank keeps the existing one;
 *     the only way to learn it is to have been given it.
 *   - It cannot create an institution on the platform. Credentials are minted by
 *     the body's operator, not requested from here — otherwise EdutrustPay would
 *     have to trust whatever a school told it about its own identity.
 */
class EdutrustPayController extends Controller
{
    /*
     * Authorisation is asserted in each method with abort_unless, which is this
     * codebase's own convention (see PrivateFileController) and is required in
     * Laravel 12, where $this->middleware() no longer exists on the base
     * controller.
     *
     * Credentials decide whose figures reach the body, so seeing them, changing
     * them and testing them are three separate permissions.
     */

    public function index(SettingsResolver $resolver)
    {
        abort_unless(auth()->user()->can('edutrustpay-reporting.view'), 403);

        $branches = Branch::orderBy('name')->get();

        $settings = EdutrustPaySetting::whereIn('branch_id', $branches->pluck('id'))->get()->keyBy('branch_id');

        return view('admin.edutrustpay.index', [
            'branches' => $branches,
            'settings' => $settings,
            // Shows whether each branch is currently resolving from the database
            // or still from .env, so nobody wonders why a saved value is ignored.
            'resolved' => $branches->mapWithKeys(fn ($b) => [$b->id => $resolver->forBranch((int) $b->id)]),
            'capabilities' => (array) config('edutrustpay.capabilities', []),
            'notDeclared' => array_diff(Capability::all(), (array) config('edutrustpay.capabilities', [])),
            'contractVersion' => ContractVersion::CURRENT,

            // Is it sending? The scheduler, the queue and what the console answered.
            'reporting' => $resolver->configured()->isNotEmpty(),
            'scheduler' => SchedulerPulse::state(),
            'schedulerSeen' => SchedulerPulse::lastSeen(),
            'schedulerSetup' => $this->schedulerSetup(),
            'sending' => $branches->mapWithKeys(fn ($b) => [$b->id => $this->sendingFor((int) $b->id)]),
        ]);
    }

    /** Everything but the payload, which can be large. */
    private const LIST_COLUMNS = [
        'id', 'branch_id', 'kind', 'period', 'sequence', 'status', 'attempts', 'next_attempt_at',
        'delivered_at', 'last_status_code', 'last_response', 'created_at', 'updated_at',
    ];

    /**
     * What one branch has sent, what is waiting and what the console refused.
     */
    private function sendingFor(int $branchId): array
    {
        return [
            'lastDelivered' => EdutrustPayOutbox::where('branch_id', $branchId)->where('status', EdutrustPayOutbox::DELIVERED)->max('delivered_at'),
            'waiting' => EdutrustPayOutbox::where('branch_id', $branchId)->where('status', EdutrustPayOutbox::PENDING)->count(),
            'problems' => $this->problems($branchId),
            'recent' => EdutrustPayOutbox::where('branch_id', $branchId)->orderByDesc('updated_at')->orderByDesc('id')->limit(6)->get(self::LIST_COLUMNS),
        ];
    }

    /**
     * Send everything now: the same jobs the scheduler runs each night, for
     * every branch that reports, run on request, with what each one said.
     *
     * For checking that reporting works without waiting for 03:00, and for
     * pushing a month out at once after a correction. It does not replace the
     * scheduler: nothing goes out again until someone presses this again, or
     * the scheduler is set up.
     */
    public function sendNow(SettingsResolver $resolver)
    {
        abort_unless(auth()->user()->can('edutrustpay-reporting.test'), 403);

        if ($resolver->configured()->isEmpty()) {
            return back()->with('error', __('Nothing was sent: no branch is switched on for reporting. Fill in a branch’s connection, tick “Send reports for this branch” and save.'));
        }

        @set_time_limit(600);
        $started = now();

        // Someone pressing this wants everything tried now, not after the
        // back-off wait. Items the console refused are tried once more too,
        // unless a later version of the same month has already gone through.
        EdutrustPayOutbox::where('status', EdutrustPayOutbox::PENDING)->update(['next_attempt_at' => null]);
        EdutrustPayOutbox::whereIn('id', $this->problems()->pluck('id'))
            ->update(['status' => EdutrustPayOutbox::PENDING, 'attempts' => 0, 'next_attempt_at' => null]);

        $steps = [
            [__('Monthly reports, last 3 months'), 'edutrustpay:report', ['--months' => 3]],
            [__('Daybooks, last 3 months'), 'edutrustpay:daybook', ['--months' => 3]],
            [__('Budget sheets, last 3 months'), 'edutrustpay:budget-sheet', ['--months' => 3]],
            [__('Check-in'), 'edutrustpay:heartbeat', []],
            [__('Delivery to the console'), 'edutrustpay:flush', []],
        ];

        $results = [];

        foreach ($steps as [$label, $command, $arguments]) {
            try {
                $code = Artisan::call($command, $arguments);
                $output = Artisan::output();
            } catch (\Throwable $e) {
                $code = 1;
                $output = $e->getMessage();
            }

            $results[] = [
                'label' => $label,
                'ok' => $code === 0,
                'output' => mb_substr(trim(preg_replace('/\e\[[\d;]*m/', '', (string) $output)), -4000),
            ];
        }

        $delivered = EdutrustPayOutbox::where('status', EdutrustPayOutbox::DELIVERED)->where('delivered_at', '>=', $started)->count();
        $waiting = EdutrustPayOutbox::where('status', EdutrustPayOutbox::PENDING)->orderBy('id')->get(self::LIST_COLUMNS);
        $problems = $this->problems();

        // The delivery job itself succeeds when it merely defers; say plainly
        // that nothing got through.
        if ($waiting->isNotEmpty() || $problems->isNotEmpty()) {
            $results[array_key_last($results)]['ok'] = false;
        }

        $stepFailed = collect($results)->contains('ok', false);

        if ($waiting->isNotEmpty()) {
            $message = __('The console could not be reached: :count item(s) are waiting here.', ['count' => $waiting->count()]).' '.self::explain($waiting->first());
        } elseif ($problems->isNotEmpty()) {
            $message = __('The console refused :count item(s):', ['count' => $problems->count()]).' '.self::explain($problems->first());
        } elseif ($stepFailed) {
            $message = __('Something went wrong while preparing the reports. See the steps below.');
        } else {
            $message = __('Done. :count item(s) delivered to the console. Months that had not changed since they were last sent were not sent again; the console already has them.', ['count' => $delivered]);
        }

        $ok = $waiting->isEmpty() && $problems->isEmpty() && ! $stepFailed;

        return redirect()->to(route('admin.edutrustpay.index').'#sending')
            ->with($ok ? 'success' : 'error', $message)
            ->with('etp_run', ['ok' => $ok, 'steps' => $results]);
    }

    /**
     * Items the console refused that still matter: those not since replaced by
     * a later version of the same month that did go through.
     */
    private function problems(?int $branchId = null)
    {
        $deliveredUpTo = EdutrustPayOutbox::where('status', EdutrustPayOutbox::DELIVERED)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('branch_id, kind, period, MAX(sequence) as seq')
            ->groupBy('branch_id', 'kind', 'period')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->branch_id.'|'.$row->kind.'|'.$row->period => (int) $row->seq]);

        // A check-in has no versions: a failed one is replaced only by a check-in
        // delivered after it for the same branch, not by an older one.
        $lastCheckIn = EdutrustPayOutbox::where('kind', 'heartbeat')
            ->where('status', EdutrustPayOutbox::DELIVERED)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->groupBy('branch_id')
            ->selectRaw('branch_id, MAX(delivered_at) as at')
            ->pluck('at', 'branch_id');

        return EdutrustPayOutbox::where('status', EdutrustPayOutbox::FAILED)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('id')
            ->get(self::LIST_COLUMNS)
            ->filter(fn ($row) => $row->kind === 'heartbeat'
                ? ! isset($lastCheckIn[$row->branch_id]) || \Illuminate\Support\Carbon::parse($lastCheckIn[$row->branch_id])->lt($row->updated_at)
                : ($deliveredUpTo[$row->branch_id.'|'.$row->kind.'|'.$row->period] ?? 0) < $row->sequence)
            ->values();
    }

    public static function kindLabel(?string $kind): string
    {
        return match ($kind) {
            'report' => __('Monthly report'),
            'daybook' => __('Daybook'),
            'budget_sheet' => __('Budget sheet'),
            'heartbeat' => __('Check-in'),
            default => ucfirst(str_replace('_', ' ', (string) $kind)),
        };
    }

    /**
     * What the console said about an item, in a sentence.
     */
    public static function explain($item): string
    {
        if ($item->status === EdutrustPayOutbox::DELIVERED) {
            return __('Received by the console.');
        }

        if ($item->last_status_code === null) {
            return $item->attempts > 0
                ? __('No answer from the console (no internet, or the console address is wrong). It stays here and is tried again on the next delivery, so nothing is lost.')
                : __('Not tried yet.');
        }

        // The console's own words: its message, else its list of errors,
        // else just the error code.
        $decoded = json_decode((string) $item->last_response, true);
        $said = null;

        if (is_array($decoded)) {
            $said = $decoded['message'] ?? null;

            if (! $said && ! empty($decoded['errors'])) {
                $said = implode(' ', array_slice(\Illuminate\Support\Arr::flatten((array) $decoded['errors']), 0, 3));
            }

            $said = $said ?: ($decoded['error'] ?? null);

            if (($decoded['error'] ?? null) === 'unsupported_contract_version') {
                $said .= ' '.__('The console is older than this school’s system: ask whoever runs the console to update it, then send again.');
            }
        }

        if ((int) $item->last_status_code === 401) {
            $said = __('The key id or secret is wrong, or the key was replaced on the console.');
        }

        // The console always answers in JSON. A web page means something else
        // answered: the address is wrong, or points at another website.
        if (! is_array($decoded) && str_contains(substr(ltrim((string) $item->last_response), 0, 200), '<')) {
            $address = app(SettingsResolver::class)->forBranch((int) $item->branch_id)['endpoint'] ?? __('the console address');

            return 'HTTP '.$item->last_status_code.': '.(in_array((int) $item->last_status_code, [404, 405], true)
                ? __('Nothing at :address answers as the EdutrustPay console: another website replied. Check this branch’s Console address below. It must be the console’s own address, such as https://edutrustpay.innovakickstarter.com, with nothing after it, and the key must have been issued on that same console.', ['address' => $address])
                : __(':address answered with a web page instead of the EdutrustPay console. Check this branch’s Console address below.', ['address' => $address]));
        }

        return 'HTTP '.$item->last_status_code.': '.mb_substr((string) ($said ?: $item->last_response), 0, 300);
    }

    /**
     * How to start the scheduler on this computer, with its real paths.
     */
    private function schedulerSetup(): array
    {
        $windows = PHP_OS_FAMILY === 'Windows';
        $php = $this->phpPath($windows);
        $base = base_path();

        return [
            'windows' => $windows,
            'command' => $windows
                ? 'schtasks /Create /SC MINUTE /MO 1 /RU SYSTEM /TN "EdutrustPay scheduler ('.basename($base).')" /TR "\"'.$php.'\" \"'.$base.'\\artisan\" schedule:run"'
                : '* * * * * cd '.$base.' && '.$php.' artisan schedule:run >> /dev/null 2>&1',
        ];
    }

    private function phpPath(bool $windows): string
    {
        $binary = (string) PHP_BINARY;
        $name = strtolower(basename($binary));

        if ($name === 'php' || $name === 'php.exe') {
            return $binary;
        }

        // On LiteSpeed (lsphp, as on Hostinger) or CGI hosting, PHP_BINARY is
        // the web server's PHP. Cron needs the command-line php, which those
        // hosts install beside it; open_basedir often hides it from is_file(),
        // so for lsphp it is named without looking.
        if ($binary !== '' && preg_match('/^(lsphp|php-cgi)/', $name)) {
            $sibling = dirname($binary).DIRECTORY_SEPARATOR.($windows ? 'php.exe' : 'php');

            if (str_starts_with($name, 'lsphp') || @is_file($sibling)) {
                return $sibling;
            }
        }

        // Under Apache or PHP-FPM, PHP_BINARY is the web server, not php.
        $ini = php_ini_loaded_file();
        $candidate = $ini ? dirname($ini).DIRECTORY_SEPARATOR.($windows ? 'php.exe' : 'php') : null;

        if ($candidate && is_file($candidate)) {
            return $candidate;
        }

        return $windows ? 'php.exe' : 'php';
    }

    public function update(Request $request, Branch $branch)
    {
        abort_unless(auth()->user()->can('edutrustpay-reporting.edit'), 403);

        $data = $request->validate([
            'enabled' => ['boolean'],
            'endpoint' => ['required_with:key_id', 'nullable', 'url', 'max:191'],
            'institution_ref' => ['nullable', 'string', 'max:64'],
            'key_id' => ['nullable', 'string', 'max:64'],
            // Blank means "leave the stored one alone" — see below.
            'secret' => ['nullable', 'string', 'min:16', 'max:191'],
        ], [], [
            'institution_ref' => __('institution reference'),
            'key_id' => __('key id'),
        ]);

        $setting = EdutrustPaySetting::firstOrNew(['branch_id' => $branch->id]);

        $setting->fill([
            'enabled' => $request->boolean('enabled'),
            'endpoint' => $data['endpoint'] ?? null,
            'institution_ref' => $data['institution_ref'] ?? null,
            'key_id' => $data['key_id'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        /*
         * A blank secret field means "keep what is stored", not "erase it".
         *
         * The field is always blank on load, because a saved secret is never
         * displayed. Treating blank as an instruction to clear would wipe a
         * working credential every time somebody corrected a typo in the
         * endpoint — and the school would go silent without anyone touching the
         * thing that matters.
         */
        if (filled($data['secret'] ?? null)) {
            $setting->secret_ciphertext = $data['secret'];

            // A new secret invalidates whatever the last test proved.
            $setting->last_tested_at = null;
            $setting->last_test_ok = null;
            $setting->last_test_message = null;
        }

        $setting->save();

        AuditLog::log('edutrustpay.settings_updated', EdutrustPaySetting::class, $setting->id, null, [
            'branch_id' => $branch->id,
            'enabled' => $setting->enabled,
            'endpoint' => $setting->endpoint,
            'key_id' => $setting->key_id,
            // Never the secret itself, only that it changed.
            'secret_changed' => filled($data['secret'] ?? null),
        ]);

        return redirect()
            ->route('admin.edutrustpay.index')
            ->with('success', __('Settings saved for :branch. Use “Test connection” to confirm the credentials work.', ['branch' => $branch->name]));
    }

    /**
     * Prove the credentials work, from here, now.
     *
     * A heartbeat rather than a report: it carries no figures, is signed exactly
     * as a report is, and is a useful thing to have sent anyway — it tells the
     * body this school is alive.
     *
     * This is the check that catches the most likely real failure, which is a
     * key rotated on the platform and never updated here. Nothing else notices
     * until month end, when the reports start bouncing.
     */
    public function test(Request $request, Branch $branch, SettingsResolver $resolver)
    {
        abort_unless(auth()->user()->can('edutrustpay-reporting.test'), 403);

        $settings = $resolver->forBranch((int) $branch->id);

        if ($settings === null) {
            return back()->with('error', __('Nothing to test yet — :branch has no endpoint, reference, key id and secret.', ['branch' => $branch->name]));
        }

        $body = json_encode(['note' => 'Connection test from the school settings screen.']);
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        try {
            $response = Http::withHeaders([
                'X-Edutrust-Key-Id' => $settings['key_id'],
                'X-Edutrust-Timestamp' => $timestamp,
                'X-Edutrust-Signature' => Signer::signRaw($body, $timestamp, $settings['secret']),
                'Accept' => 'application/json',
            ])->withBody($body, 'application/json')
                ->timeout(15)
                ->post(rtrim($settings['endpoint'], '/').'/api/v1/heartbeat');
        } catch (\Throwable $e) {
            return $this->recordTest($branch, false, __('Could not reach :endpoint. Reports will queue locally and retry, so this is not necessarily a problem with the credentials.', [
                'endpoint' => $settings['endpoint'],
            ]));
        }

        if ($response->successful()) {
            return $this->recordTest($branch, true, __('Accepted by :endpoint. A heartbeat was recorded against this school.', [
                'endpoint' => $settings['endpoint'],
            ]));
        }

        if ($response->status() === 401) {
            /*
             * The console answers an unknown key and a bad signature
             * identically, on purpose, so that live key ids cannot be
             * enumerated. Say what to check rather than guessing which it was.
             */
            return $this->recordTest($branch, false, __('Rejected. The key id or secret is wrong, or the key has been rotated or revoked on the platform. Ask the operator to reissue and paste the new values here.'));
        }

        return $this->recordTest($branch, false, __('Unexpected response (HTTP :status) from :endpoint.', [
            'status' => $response->status(),
            'endpoint' => $settings['endpoint'],
        ]));
    }

    private function recordTest(Branch $branch, bool $ok, string $message)
    {
        EdutrustPaySetting::where('branch_id', $branch->id)->update([
            'last_tested_at' => now(),
            'last_test_ok' => $ok,
            'last_test_message' => mb_substr($message, 0, 500),
        ]);

        AuditLog::log('edutrustpay.connection_tested', EdutrustPaySetting::class, null, null, [
            'branch_id' => $branch->id,
            'ok' => $ok,
        ]);

        return back()->with($ok ? 'success' : 'error', $message);
    }
}
