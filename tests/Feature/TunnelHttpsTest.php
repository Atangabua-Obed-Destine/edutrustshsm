<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A school served through a tunnel: https to the browser, plain http to this
 * server. Links the app writes must come out https, or the browser blocks the
 * page's own requests (recording a payment, for one) as mixed content.
 */
class TunnelHttpsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::get('/__link-probe', fn () => route('admin.collect-fees.store-payment'))->middleware('web');
    }

    public function test_the_tunnel_on_this_machine_is_believed_about_https(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('http://school.example.org/__link-probe')
            ->assertOk()
            ->assertSee('https://school.example.org/', false);
    }

    public function test_a_stranger_on_the_network_is_not_believed(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.50'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('http://school.example.org/__link-probe')
            ->assertOk()
            ->assertSee('http://school.example.org/', false);
    }

    public function test_a_forwarded_host_is_never_written_into_links(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.example.com'])
            ->get('http://school.example.org/__link-probe')
            ->assertOk()
            ->assertDontSee('evil.example.com', false);
    }
}
