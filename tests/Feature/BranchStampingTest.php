<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FeeCategory;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BranchContext::current() returns 0 to mean "no branch context". The
 * BelongsToBranch creating hook used to stamp that 0 straight onto branch_id,
 * which is not a real branch — it violates the foreign key on a strict
 * connection and silently orphans the row where the FK is not enforced.
 */
class BranchStampingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_no_branches_does_not_stamp_branch_zero(): void
    {
        Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        // With several branches there is a choice to make, so an unlinked
        // account gets none. (With one branch it works there: see below.)
        Branch::firstOrCreate(['code' => 'SECOND'], ['name' => 'Second', 'is_active' => true]);

        // Deliberately NOT attached to any branch.
        $orphan = User::create([
            'first_name' => 'No', 'last_name' => 'Branch',
            'email' => 'nobranch@example.test', 'password' => 'password',
            'role' => 'admin', 'is_active' => true,
        ]);

        $this->actingAs($orphan);
        BranchContext::flush();

        $this->assertSame(0, BranchContext::current(), 'no accessible branches means no context');

        $category = FeeCategory::create(['name' => 'Tuition', 'code' => 'TUI', 'is_active' => true]);

        $this->assertNull($category->branch_id, 'branch_id must be left null, never 0');
    }

    public function test_in_a_one_branch_school_an_unlinked_user_works_in_that_branch(): void
    {
        $branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        $teacher = User::create([
            'first_name' => 'Hr', 'last_name' => 'Created',
            'email' => 'hr@example.test', 'password' => 'password',
            'role' => 'teacher', 'is_active' => true,
        ]);

        $this->actingAs($teacher);
        BranchContext::flush();

        $this->assertSame($branch->id, BranchContext::current());
    }

    public function test_a_user_with_a_branch_still_gets_stamped(): void
    {
        $branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        $admin = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'admin', 'is_active' => true,
        ]);
        $admin->branches()->attach($branch->id, ['is_default' => true]);

        $this->actingAs($admin);
        BranchContext::flush();

        $category = FeeCategory::create(['name' => 'Tuition', 'code' => 'TUI', 'is_active' => true]);

        $this->assertSame($branch->id, $category->branch_id);
    }
}
