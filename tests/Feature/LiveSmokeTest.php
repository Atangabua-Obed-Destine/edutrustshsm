<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Renders every admin GET screen against the real MySQL data.
 *
 * The rest of the suite runs on SQLite fixtures, which has already hidden
 * four production-only failures: an enum that cannot be renamed, a column
 * that is NULL in practice, MySQL-only SQL, and a health check querying a
 * column that does not exist. This signs in as a real admin and opens every
 * page, read-only, to see what the actual data does to them.
 *
 * Skipped by default because it needs a populated database. Run it with:
 *
 *     LIVE_SMOKE=1 php artisan test --filter=LiveSmokeTest
 */
class LiveSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml forces DB_DATABASE=:memory: onto every connection, so
        // the real one has to be named here.
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

        \Illuminate\Support\Facades\DB::purge('mysql');
    }

    public function test_every_admin_screen_renders(): void
    {
        $admin = User::on('mysql')
            ->whereIn('role', ['super_admin', 'admin'])
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($admin, 'no admin user in the live database');

        $this->actingAs($admin);

        $broken = [];
        $checked = 0;

        foreach ($this->targets() as $name => $params) {
            try {
                $url = route($name, $params, false);
            } catch (\Throwable $e) {
                $broken[] = "$name — cannot build url: ".$e->getMessage();

                continue;
            }

            $response = $this->get($url);
            $checked++;

            $status = $response->getStatusCode();

            if ($status >= 400) {
                $detail = $status;

                if ($status >= 500) {
                    $body = strip_tags($response->getContent());
                    $detail .= ' — '.trim(preg_replace('/\s+/', ' ', mb_substr($body, 0, 220)));
                }

                $broken[] = "$name [$url] → $detail";
            }
        }

        fwrite(STDERR, "\n  screens checked: $checked\n");

        $this->assertSame([], $broken, "Screens that did not render:\n  - ".implode("\n  - ", $broken));
    }

    public function test_every_parent_portal_screen_renders(): void
    {
        $guardian = \App\Models\Guardian::on('mysql')
            ->whereHas('students')
            ->first();

        if (! $guardian) {
            $this->markTestSkipped('no guardian with children in the live database');
        }

        $this->actingAs($guardian, 'guardians');

        $student = \App\Models\Student::on('mysql')->where('guardian_id', $guardian->id)->first();

        $broken = [];
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (! $name || ! str_starts_with($name, 'parent.') || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            // Auth pages belong to a signed-out guardian, and a file needs
            // its own record to mean anything.
            if (in_array($name, ['parent.login', 'parent.claim'], true) || str_contains($name, 'files.')) {
                continue;
            }

            $params = [];
            $skip = false;

            foreach ($route->parameterNames() as $parameter) {
                $value = match ($parameter) {
                    'student' => $student?->id,
                    'notice' => \App\Models\Announcement::on('mysql')->value('id'),
                    'submission' => \App\Models\ParentPaymentSubmission::on('mysql')->value('id'),
                    // Must belong to this guardian's own child and be
                    // published, or a 403 is the guard doing its job rather
                    // than a broken page.
                    'termResult' => \App\Models\TermResult::on('mysql')
                        ->where('is_published', true)
                        ->whereHas('enrollment', fn ($q) => $q->where('student_id', $student?->id))
                        ->value('id'),
                    default => null,
                };

                if ($value === null) {
                    $skip = true;
                    break;
                }

                $params[$parameter] = $value;
            }

            if ($skip) {
                continue;
            }

            $response = $this->get(route($name, $params, false));
            $checked++;

            if ($response->getStatusCode() >= 400) {
                $detail = $response->getStatusCode();

                if ($detail >= 500) {
                    $detail .= ' — '.trim(preg_replace('/\s+/', ' ', mb_substr(strip_tags($response->getContent()), 0, 220)));
                }

                $broken[] = "$name → $detail";
            }
        }

        fwrite(STDERR, "
  parent screens checked: $checked
");

        $this->assertSame([], $broken, "Parent screens that did not render:
  - ".implode("
  - ", $broken));
    }

    /**
     * Every named admin GET route, with real ids filled in where one is needed.
     *
     * @return array<string, array<string, mixed>>
     */
    private function targets(): array
    {
        $ids = [
            'student' => \App\Models\Student::on('mysql')->value('id'),
            'form' => \App\Models\Form::on('mysql')->value('id'),
            'council' => \App\Models\ClassCouncil::on('mysql')->value('id'),
            'announcement' => \App\Models\Announcement::on('mysql')->value('id'),
            'event' => \App\Models\Event::on('mysql')->value('id'),
            'guardian' => \App\Models\Guardian::on('mysql')->value('id'),
            'termResult' => \App\Models\TermResult::on('mysql')->value('id'),
            'budget' => \App\Models\Budget::on('mysql')->value('id'),
            'user' => \App\Models\User::on('mysql')->value('id'),
        ];

        $targets = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (! $name || ! str_starts_with($name, 'admin.') || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            // These need a record in a particular state to say anything:
            // a private file needs its owner, and a leaving certificate is
            // refused — correctly — for a pupil who has not left.
            if (str_contains($name, 'files.') || str_contains($name, 'leaving-certificate')) {
                continue;
            }

            $params = [];
            $skip = false;

            foreach ($route->parameterNames() as $parameter) {
                $value = $ids[$parameter] ?? null;

                if ($value === null) {
                    $skip = true;
                    break;
                }

                $params[$parameter] = $value;
            }

            if (! $skip) {
                $targets[$name] = $params;
            }
        }

        ksort($targets);

        return $targets;
    }
}
