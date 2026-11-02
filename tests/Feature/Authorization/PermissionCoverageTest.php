<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every action must be reachable by some permission that exists.
 *
 * Eleven were not: saving a class register needed "attendance.create",
 * recording a payment "payment.create", generating a payslip
 * "payroll.create" — none of which the permission list defines — so only
 * the super admin could do any of them, and teachers, bursars and HR were
 * refused on the first day.
 */
class PermissionCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_action_needs_a_permission_nobody_can_hold(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $known = Permission::pluck('name')->flip();
        $unreachable = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if (! str_contains($action, '@')) {
                continue;
            }
            [$class, $method] = explode('@', $action);
            if (! class_exists($class) || ! in_array(HasMiddleware::class, class_implements($class), true)) {
                continue;
            }

            foreach ($class::middleware() as $m) {
                if (! $m instanceof Middleware || ! is_string($m->middleware) || ! str_starts_with($m->middleware, 'permission:')) {
                    continue;
                }
                if ($m->only !== null && ! in_array($method, (array) $m->only, true)) {
                    continue;
                }
                if ($m->except !== null && in_array($method, (array) $m->except, true)) {
                    continue;
                }
                $names = explode('|', substr($m->middleware, strlen('permission:')));
                if (! collect($names)->contains(fn ($n) => $known->has($n))) {
                    $unreachable[] = $route->getName().' needs '.implode('|', $names);
                }
            }
        }

        $this->assertSame([], array_values(array_unique($unreachable)));
    }
}
