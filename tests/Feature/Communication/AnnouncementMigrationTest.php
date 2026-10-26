<?php

namespace Tests\Feature\Communication;

use App\Models\Announcement;
use App\Models\AnnouncementCategory;
use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The PTA announcements table becomes the school's notice board.
 *
 * A rename is the one migration that can lose other people's data, so this
 * checks the shape it leaves behind rather than trusting it.
 */
class AnnouncementMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_pta_table_is_now_the_school_wide_one(): void
    {
        $this->assertTrue(Schema::hasTable('announcements'));
        $this->assertFalse(Schema::hasTable('pta_announcements'));

        $this->assertTrue(Schema::hasColumns('announcements', [
            'category_id', 'reference', 'scope', 'audience', 'attachment',
            'target_form_id', 'target_class_section_id', 'published_at', 'expires_at',
        ]));

        // The old column did two jobs; it is now only about reach.
        $this->assertFalse(Schema::hasColumn('announcements', 'audience_old'));
    }

    public function test_the_default_categories_are_seeded_per_branch(): void
    {
        $branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        $slugs = AnnouncementCategory::where('branch_id', $branch->id)->pluck('slug')->sort()->values()->all();

        $this->assertSame(
            ['academic', 'finance', 'general', 'health-safety', 'holiday', 'pta'],
            $slugs
        );
    }

    public function test_references_run_in_sequence_within_a_branch(): void
    {
        $branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        $this->assertSame('NOT-'.now()->year.'-0001', Announcement::nextReference($branch->id));

        Announcement::create([
            'branch_id' => $branch->id,
            'reference' => Announcement::nextReference($branch->id),
            'title' => 'Term opens', 'body' => 'Monday.',
            'scope' => 'all', 'audience' => 'guardians',
        ]);

        $this->assertSame('NOT-'.now()->year.'-0002', Announcement::nextReference($branch->id));
    }
}
