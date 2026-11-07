<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * School years that happened before the school used this system.
 *
 * Their results were proclaimed on paper report cards and decided who moved
 * up. They are recorded here as they were given, never recomputed: a second,
 * recomputed version of a rank a family already holds would be two truths.
 *
 *  - academic_sessions.is_recorded marks such a year. It is never current, and
 *    nothing that bills, takes registers or computes results runs on it.
 *  - term_results.source tells a recorded result from a computed one.
 *  - recorded_sheets is one class's results for one term of that year, the
 *    unit one person enters and another verifies against the paper.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_sessions', function (Blueprint $table) {
            $table->boolean('is_recorded')->default(false)->after('is_current');
        });

        // The year's average and position as written on last year's final
        // report card, kept rather than recomputed.
        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->boolean('final_written')->default(false)->after('final_rank');
        });

        Schema::table('term_results', function (Blueprint $table) {
            $table->string('source', 10)->default('computed')->after('is_published');
            $table->foreignId('recorded_by')->nullable()->after('source')->constrained('users')->nullOnDelete();
        });

        Schema::create('recorded_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('class_section_id')->constrained('class_sections')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->string('status', 10)->default('draft'); // draft | verified
            $table->decimal('out_of', 5, 1)->default(20);
            $table->string('evidence_path')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('entered_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['academic_session_id', 'class_section_id', 'term_id'], 'recorded_sheet_unique');
        });

        // Recording, verifying and viewing a past year. The head teacher
        // verifies; whoever enters cannot verify their own sheet.
        if (Schema::hasTable('permissions')) {
            $ids = [];
            foreach (['view' => 'View', 'record' => 'Record', 'verify' => 'Verify'] as $action => $label) {
                $name = 'past-year.'.$action;
                $ids[$action] = DB::table('permissions')->where('name', $name)->value('id')
                    ?? DB::table('permissions')->insertGetId([
                        'name' => $name, 'display_name' => $label, 'group_name' => 'Past Year',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
            }

            $grants = ['super_admin' => ['view', 'record', 'verify'], 'admin' => ['view', 'record', 'verify'], 'accountant' => ['view']];
            foreach ($grants as $role => $actions) {
                $roleId = DB::table('roles')->where('name', $role)->value('id');
                foreach ($roleId ? $actions : [] as $action) {
                    DB::table('role_permission')->insertOrIgnore([
                        'role_id' => $roleId, 'permission_id' => $ids[$action], 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('group_name', 'Past Year')->delete();
        Schema::dropIfExists('recorded_sheets');
        Schema::table('term_results', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recorded_by');
            $table->dropColumn('source');
        });
        Schema::table('academic_sessions', function (Blueprint $table) {
            $table->dropColumn('is_recorded');
        });
        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->dropColumn('final_written');
        });
    }
};
