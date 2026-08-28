<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The app throws 403s routinely (role and permission middleware) but had no
 * resources/views/errors at all, so users hit Laravel's raw debug page.
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_forbidden_page_renders_the_branded_403(): void
    {
        $staff = User::create([
            'first_name' => 'Sam', 'last_name' => 'Staff',
            'email' => 'staff@example.test', 'password' => 'password',
            'role' => 'staff', 'is_active' => true,
        ]);

        $response = $this->actingAs($staff)->get(route('admin.users.index'));

        $response->assertForbidden();
        $response->assertSee('403', false);
        $response->assertSee('Access denied', false);
    }

    public function test_a_missing_page_renders_the_branded_404(): void
    {
        $this->get('/admin/there-is-nothing-here')
            ->assertNotFound()
            ->assertSee('404', false)
            ->assertSee('Page not found', false);
    }

    public function test_the_500_page_does_not_leak_the_exception_message(): void
    {
        $rendered = view('errors.500', [
            'exception' => new \RuntimeException('SQLSTATE secret connection details'),
        ])->render();

        $this->assertStringNotContainsString('SQLSTATE', $rendered);
        $this->assertStringContainsString('unexpected error', $rendered);
    }
}
