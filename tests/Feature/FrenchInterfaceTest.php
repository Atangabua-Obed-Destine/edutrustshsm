<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrenchInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_interface_renders_in_french(): void
    {
        Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        $admin = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);

        $html = $this->withSession(['locale' => 'fr'])
            ->actingAs($admin)
            ->get(route('admin.audit-log.index'))
            ->assertSuccessful()
            ->getContent();

        $this->assertStringContainsString('Journal d', $html, 'the audit log heading should be French');
        $this->assertStringNotContainsString('Who changed what', $html);
    }

    public function test_the_language_switcher_changes_the_locale(): void
    {
        $this->get(route('lang.switch', 'fr'))->assertRedirect();
        $this->assertSame('fr', session('locale'));

        $this->get(route('lang.switch', 'en'))->assertRedirect();
        $this->assertSame('en', session('locale'));
    }

    public function test_an_unknown_locale_is_ignored(): void
    {
        $this->withSession(['locale' => 'fr'])->get(route('lang.switch', 'de'));

        $this->assertSame('fr', session('locale'), 'only en/fr are accepted');
    }
}
