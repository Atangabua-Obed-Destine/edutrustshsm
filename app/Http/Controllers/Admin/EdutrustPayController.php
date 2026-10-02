<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\EdutrustPaySetting;
use App\Services\EdutrustPay\SettingsResolver;
use EdutrustPay\Contract\Capability;
use EdutrustPay\Contract\ContractVersion;
use EdutrustPay\Contract\Signer;
use Illuminate\Http\Request;
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
        ]);
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
