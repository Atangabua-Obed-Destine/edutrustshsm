<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login');
    }

    public function test_responses_carry_baseline_security_headers(): void
    {
        $this->get(route('login'))
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_hsts_is_not_sent_over_plain_http(): void
    {
        // Setting HSTS on a plain-HTTP install would be actively harmful.
        $this->get(route('login'))->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_repeated_failed_logins_are_throttled(): void
    {
        User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'admin@example.test', 'password' => 'wrong'])
                ->assertStatus(302);
        }

        // The 6th attempt within the minute is refused outright.
        $this->post('/login', ['email' => 'admin@example.test', 'password' => 'wrong'])
            ->assertStatus(429);
    }

    public function test_the_parent_portal_login_is_throttled_too(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('parent.login.submit'), ['login_email' => 'p@example.test', 'password' => 'wrong']);
        }

        $this->post(route('parent.login.submit'), ['login_email' => 'p@example.test', 'password' => 'wrong'])
            ->assertStatus(429);
    }
}
