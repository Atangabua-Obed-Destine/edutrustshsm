<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lift announcements out of the PTA module.
 *
 * The PTA was the only part of the school that could speak to parents: the
 * mechanism — targeting, draft/publish, expiry — was already right, but a head
 * teacher had no way to post a notice. The table becomes the school's, and the
 * PTA becomes one category on it.
 *
 * The old `audience` column was doing two jobs at once, naming how widely a
 * notice reaches (whole school, a form, a class). It is renamed to `scope`, and
 * `audience` now says who it reaches — guardians, staff, or both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            // Carried through to the badge on the notice board, so a reader can
            // tell a holiday from a fee deadline at a glance.
            $table->string('colour', 20)->default('slate');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'slug']);
        });

        Schema::rename('pta_announcements', 'announcements');

        Schema::table('announcements', function (Blueprint $table) {
            $table->renameColumn('audience', 'scope');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('branch_id')
                ->constrained('announcement_categories')->nullOnDelete();
            // Staff refer to a notice in writing, so it needs a handle that is
            // not its database id.
            $table->string('reference', 30)->nullable()->after('category_id');
            $table->string('audience', 20)->default('guardians')->after('scope');
            $table->string('attachment')->nullable()->after('body');

            $table->unique(['branch_id', 'reference']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('announcement_categories')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('venue', 150)->nullable();
            // Same reach as a notice: a nursery sports day is not news to a
            // secondary parent.
            $table->string('scope', 20)->default('all');
            $table->string('audience', 20)->default('guardians');
            $table->foreignId('target_form_id')->nullable()->constrained('forms')->nullOnDelete();
            $table->foreignId('target_class_section_id')->nullable()->constrained('class_sections')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'starts_on'], 'events_branch_start_index');
        });

        $this->seedCategories();
    }

    /**
     * The categories a school actually posts under. PTA is seeded first and
     * every existing announcement is filed under it, since that is what they
     * were.
     */
    private function seedCategories(): void
    {
        $branchIds = DB::table('branches')->pluck('id');
        if ($branchIds->isEmpty()) {
            $branchIds = collect([null]);
        }

        $defaults = [
            ['name' => 'PTA', 'slug' => 'pta', 'colour' => 'emerald'],
            ['name' => 'General', 'slug' => 'general', 'colour' => 'slate'],
            ['name' => 'Academic', 'slug' => 'academic', 'colour' => 'blue'],
            ['name' => 'Finance', 'slug' => 'finance', 'colour' => 'amber'],
            ['name' => 'Health & Safety', 'slug' => 'health-safety', 'colour' => 'rose'],
            ['name' => 'Holiday', 'slug' => 'holiday', 'colour' => 'violet'],
        ];

        $now = now();

        foreach ($branchIds as $branchId) {
            foreach ($defaults as $default) {
                DB::table('announcement_categories')->insert($default + [
                    'branch_id' => $branchId,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $ptaId = DB::table('announcement_categories')
                ->where('slug', 'pta')
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->value('id');

            DB::table('announcements')
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->whereNull('category_id')
                ->update(['category_id' => $ptaId]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events');

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'reference']);
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['reference', 'audience', 'attachment']);
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->renameColumn('scope', 'audience');
        });

        Schema::rename('announcements', 'pta_announcements');

        Schema::dropIfExists('announcement_categories');
    }
};
