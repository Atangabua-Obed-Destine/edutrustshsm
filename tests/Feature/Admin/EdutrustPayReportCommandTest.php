<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\EdutrustPayOutbox;
use App\Models\EdutrustPaySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * How months get filed: never the unfinished one, never twice when nothing
 * changed, and a conflict refiled openly rather than left failed in the outbox.
 */
class EdutrustPayReportCommandTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-07 10:00:00');

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'ST JEROME', 'is_active' => true]);

        EdutrustPaySetting::create([
            'branch_id' => $this->branch->id,
            'enabled' => true,
            'endpoint' => 'https://console.example.test',
            'institution_ref' => '11111111-1111-4111-8111-111111111111',
            'key_id' => 'etp_test',
            'secret_ciphertext' => 'a-test-secret-of-sufficient-length',
        ]);
    }

    public function test_months_end_at_the_last_complete_month(): void
    {
        Http::fake(['*' => Http::response(['status' => 'accepted'], 202)]);

        $this->artisan('edutrustpay:report --months=2 --send');

        $this->assertSame(
            ['2026-09', '2026-10'],
            EdutrustPayOutbox::where('kind', 'report')->orderBy('period')->pluck('period')->all(),
            'November has not finished and must not be filed.'
        );
    }

    public function test_to_date_files_the_month_in_progress_as_provisional_then_final(): void
    {
        Http::fake(['*' => Http::response(['status' => 'accepted'], 202)]);

        $this->artisan('edutrustpay:report --months=2 --to-date --send');

        $this->assertSame(
            ['2026-09', '2026-10', '2026-11'],
            EdutrustPayOutbox::where('kind', 'report')->orderBy('period')->pluck('period')->all()
        );
        $november = EdutrustPayOutbox::where('kind', 'report')->where('period', '2026-11')->sole();
        $this->assertSame('provisional', json_decode($november->payload, true)['status']);

        // Once November is well over, the nightly run files it again as final.
        $this->travelTo('2026-12-15 03:00:00');
        $this->artisan('edutrustpay:report --months=3 --to-date --send');

        $latest = EdutrustPayOutbox::where('kind', 'report')->where('period', '2026-11')->orderByDesc('sequence')->first();
        $this->assertSame(2, (int) $latest->sequence);
        $this->assertSame('final', json_decode($latest->payload, true)['status']);
        $this->assertSame(EdutrustPayOutbox::DELIVERED, $latest->status);
    }

    public function test_an_unchanged_month_is_not_filed_again(): void
    {
        Http::fake(['*' => Http::response(['status' => 'accepted'], 202)]);

        $this->artisan('edutrustpay:report 2026-10 --send');
        $this->travel(1)->days();
        $this->artisan('edutrustpay:report 2026-10 --send');

        $this->assertSame(1, EdutrustPayOutbox::where('period', '2026-10')->count());
    }

    public function test_a_conflict_is_refiled_at_the_next_sequence(): void
    {
        Http::fakeSequence()
            ->push(['error' => 'sequence_conflict', 'current_sequence' => 3], 409)
            ->push(['status' => 'accepted'], 202);

        $this->artisan('edutrustpay:report 2026-10 --send');

        $latest = EdutrustPayOutbox::where('period', '2026-10')->orderByDesc('sequence')->first();

        $this->assertSame(4, (int) $latest->sequence);
        $this->assertSame(EdutrustPayOutbox::DELIVERED, $latest->status);
    }

    public function test_a_rejected_month_is_sent_again_on_the_next_run(): void
    {
        Http::fakeSequence()
            ->push(['error' => 'unauthorized'], 401)
            ->push(['status' => 'accepted'], 202);

        $this->artisan('edutrustpay:report 2026-10 --send');
        $this->assertSame(EdutrustPayOutbox::FAILED, EdutrustPayOutbox::where('period', '2026-10')->first()->status);

        // The key has been fixed since: the same month goes through at the same sequence.
        $this->artisan('edutrustpay:report 2026-10 --send');

        $row = EdutrustPayOutbox::where('period', '2026-10')->sole();
        $this->assertSame(EdutrustPayOutbox::DELIVERED, $row->status);
        $this->assertSame(1, (int) $row->sequence);
    }
}
