<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LevelContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Opens every admin screen once per school level, against the real data.
 *
 * Data is scoped by level in thirty controllers, but nothing had ever checked
 * what those screens look like from the other side. A screen that is correct
 * in Secondary and empty, broken or nonsensical in Nursery / Primary is the
 * thing this is looking for.
 *
 * Skipped unless LIVE_SMOKE=1, because it needs a populated database.
 */
class LevelModeSweepTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! env('LIVE_SMOKE')) {
            $this->markTestSkipped('Set LIVE_SMOKE=1 to run this against the real database.');
        }

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'scholartrack_db',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => '',
        ]);

        DB::purge('mysql');
    }

    public function test_every_screen_in_both_levels(): void
    {
        $admin = User::on('mysql')->whereIn('role', ['super_admin', 'admin'])->where('is_active', true)->first();
        $this->assertNotNull($admin);

        $report = [];
        $broken = [];

        foreach (['secondary', 'nursery_primary'] as $level) {
            foreach ($this->targets() as $name => $params) {
                $this->actingAs($admin);
                $this->withSession([LevelContext::SESSION_KEY => $level]);

                $response = $this->get(route($name, $params, false));
                $status = $response->getStatusCode();

                if ($status >= 500) {
                    $broken[] = "[$level] $name → $status";

                    continue;
                }

                $report[$name][$level] = $status === 200
                    ? $this->shape($response->getContent())
                    : $status;
            }
        }

        // Screens whose two levels differ enough to be worth a human look.
        $notable = [];

        foreach ($report as $name => $levels) {
            $sec = $levels['secondary'] ?? null;
            $pri = $levels['nursery_primary'] ?? null;

            if ($sec !== $pri) {
                $notable[] = sprintf('%-44s secondary: %-14s primary: %s', $name, $sec, $pri);
            }
        }

        fwrite(STDERR, "\n── screens that differ by level ──\n  ".implode("\n  ", $notable)."\n");
        fwrite(STDERR, "\n  screens: ".count($report)."  differing: ".count($notable)."\n");

        $this->assertSame([], $broken, "Broken in one level:\n  - ".implode("\n  - ", $broken));
    }

    /**
     * A crude read of whether a page has anything on it.
     *
     * Counting table rows is enough to tell "this screen has content" from
     * "this screen is an empty shell", which is the distinction that matters.
     */
    private function shape(string $html): string
    {
        $rows = substr_count($html, '<tr');
        $options = substr_count($html, '<option');

        return match (true) {
            $rows > 2 => 'rows:'.$rows,
            $options > 4 => 'options:'.$options,
            default => 'EMPTY',
        };
    }

    /** @return array<string, array<string, mixed>> */
    private function targets(): array
    {
        $ids = [
            'student' => \App\Models\Student::on('mysql')->value('id'),
            'form' => \App\Models\Form::on('mysql')->where('school_level', 'secondary')->value('id'),
            'guardian' => \App\Models\Guardian::on('mysql')->value('id'),
        ];

        $targets = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (! $name || ! str_starts_with($name, 'admin.') || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if (preg_match('/export|download|\.pdf|csv|files\.|print|certificate|minutes|slip|create|edit/', $name)) {
                continue;
            }

            $params = [];
            $skip = false;

            foreach ($route->parameterNames() as $parameter) {
                if (! isset($ids[$parameter]) || $ids[$parameter] === null) {
                    $skip = true;
                    break;
                }
                $params[$parameter] = $ids[$parameter];
            }

            if (! $skip) {
                $targets[$name] = $params;
            }
        }

        ksort($targets);

        return $targets;
    }
}
