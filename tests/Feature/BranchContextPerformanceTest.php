<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BranchScope runs on every query of ~70 models, and each pass used to call
 * accessibleIds() two or three times — every one a live branch_user lookup.
 */
class BranchContextPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolving_the_branch_does_not_requery_per_call(): void
    {
        $branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        $user = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'admin', 'is_active' => true,
        ]);
        $user->branches()->attach($branch->id, ['is_default' => true]);

        $this->actingAs($user);
        BranchContext::flush();

        BranchContext::current(); // warm

        DB::enableQueryLog();
        for ($i = 0; $i < 50; $i++) {
            BranchContext::current();
            BranchContext::accessibleIds();
            BranchContext::isAllBranches();
        }
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(0, $count, "resolving branch context issued {$count} queries");
    }

    public function test_the_memo_does_not_leak_between_requests(): void
    {
        $main = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        $annex = Branch::create(['name' => 'Annex', 'code' => 'ANX', 'is_active' => true]);

        $a = User::create([
            'first_name' => 'A', 'last_name' => 'One', 'email' => 'a@example.test',
            'password' => 'password', 'role' => 'admin', 'is_active' => true,
        ]);
        $a->branches()->attach($main->id, ['is_default' => true]);

        $b = User::create([
            'first_name' => 'B', 'last_name' => 'Two', 'email' => 'b@example.test',
            'password' => 'password', 'role' => 'admin', 'is_active' => true,
        ]);
        $b->branches()->attach($annex->id, ['is_default' => true]);

        // Two requests as different users in the same process must not share a memo.
        $this->actingAs($a)->get(route('login'));
        $this->assertSame([$main->id], BranchContext::accessibleIds());

        $this->actingAs($b)->get(route('login'));
        BranchContext::flush();
        $this->assertSame([$annex->id], BranchContext::accessibleIds());
    }
}
