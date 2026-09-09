<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Services\ConfigurationHealthService;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * "What have you not configured yet" — read-only, and gated with the same
 * permission as school settings, since it exists to send an admin there.
 */
class ConfigurationHealthController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'school-settings';

    public function __construct(private ConfigurationHealthService $health) {}

    public function index()
    {
        return view('admin.configuration-health.index', $this->health->report());
    }
}
