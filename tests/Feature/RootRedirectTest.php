<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RootRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_signed_in_users_are_sent_to_their_own_home(): void
    {
        $accountant = User::create([
            'first_name' => 'Ada', 'last_name' => 'Accountant',
            'email' => 'accountant@example.test', 'password' => 'password',
            'role' => 'accountant', 'is_active' => true,
        ]);

        $this->actingAs($accountant)
            ->get('/')
            ->assertRedirect(route('admin.account.income.index'));
    }
}
