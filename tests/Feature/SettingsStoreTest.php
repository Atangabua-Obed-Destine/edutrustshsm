<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsStoreTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        $this->admin = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);
    }

    public function test_values_round_trip_with_their_type(): void
    {
        $this->actingAs($this->admin);

        Setting::put('receipt.footer', 'Thank you', 'receipt');
        Setting::put('receipt.show_logo', true, 'receipt', 'bool');
        Setting::put('mail.port', 587, 'mail', 'int');
        Setting::put('report_card.grades', ['A', 'B'], 'report-card', 'json');

        $this->assertSame('Thank you', setting('receipt.footer'));
        $this->assertTrue(setting('receipt.show_logo'));
        $this->assertSame(587, setting('mail.port'));
        $this->assertSame(['A', 'B'], setting('report_card.grades'));
    }

    public function test_a_missing_key_returns_the_default(): void
    {
        $this->actingAs($this->admin);

        $this->assertNull(setting('nothing.here'));
        $this->assertSame('fallback', setting('nothing.here', 'fallback'));
    }

    public function test_secrets_are_stored_encrypted(): void
    {
        $this->actingAs($this->admin);

        Setting::put('mail.password', 'hunter2', 'mail', 'string', encrypted: true);

        $raw = Setting::query()->where('key', 'mail.password')->value('value');

        $this->assertNotSame('hunter2', $raw, 'the secret must not be readable in the table');
        $this->assertSame('hunter2', setting('mail.password'));
    }

    public function test_settings_are_scoped_per_branch(): void
    {
        $other = Branch::create(['name' => 'Annex', 'code' => 'ANX', 'is_active' => true]);
        $this->admin->branches()->attach($other->id);

        $this->actingAs($this->admin);

        session(['active_branch_id' => $this->branch->id]);
        Setting::put('receipt.footer', 'Main campus', 'receipt');

        session(['active_branch_id' => $other->id]);
        Setting::forget();
        \App\Support\BranchContext::flush();
        $this->assertNull(setting('receipt.footer'), 'a branch must not see another branch value');

        Setting::put('receipt.footer', 'Annex campus', 'receipt');
        $this->assertSame('Annex campus', setting('receipt.footer'));

        session(['active_branch_id' => $this->branch->id]);
        Setting::forget();
        \App\Support\BranchContext::flush();
        $this->assertSame('Main campus', setting('receipt.footer'));
    }

    public function test_reading_settings_repeatedly_does_not_requery(): void
    {
        $this->actingAs($this->admin);
        Setting::put('receipt.footer', 'Thank you', 'receipt');

        setting('receipt.footer'); // warm

        \Illuminate\Support\Facades\DB::enableQueryLog();
        for ($i = 0; $i < 30; $i++) {
            setting('receipt.footer');
        }
        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->assertCount(0, $queries);
    }

    public function test_a_tab_can_be_saved_through_the_settings_screen(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.group.update', 'receipt'), [
                'receipt_header' => 'Longla Comprehensive College',
                'receipt_footer' => 'Fees are non-refundable.',
                'receipt_show_logo' => '1',
                'receipt_signature_label' => 'Bursar',
            ])
            ->assertRedirect();

        Setting::forget();

        $this->assertSame('Longla Comprehensive College', setting('receipt.header'));
        $this->assertSame('Bursar', setting('receipt.signature_label'));
        $this->assertTrue(setting('receipt.show_logo'));
    }

    public function test_an_unknown_settings_tab_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.group.update', 'not-a-tab'), [])
            ->assertNotFound();
    }
}
