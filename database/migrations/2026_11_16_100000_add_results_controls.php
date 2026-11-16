<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The controls a school keeps over results once they leave the teacher, as
 * the reference system has them:
 *
 *  - result_holds: a pupil's report card for a term kept from the parent
 *    portal, with the reason, and released with a reason of its own. The
 *    history stays.
 *  - mark_corrections: one mark changed after it was approved or published,
 *    never silently: the old and new mark, why, and who.
 *  - marks_entry_deadlines: the day mark entry closes for a sequence in a
 *    school year. Sequences are the same every year, so the date cannot live
 *    on the sequence (its unused marks_entry_deadline column would close
 *    entry for ever after the first year).
 *
 * The permissions sit in their own group, Results Control, because teachers
 * hold the whole Marks Entry and Report Card groups. They go to whoever may
 * already publish results.
 */
return new class extends Migration
{
    private array $permissions = [
        'results-control.withhold' => 'Withhold',
        'results-control.correct' => 'Correct',
        'results-control.deadline' => 'Deadline',
    ];

    public function up(): void
    {
        Schema::create('result_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->text('reason');
            $table->foreignId('held_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('held_at')->nullable();
            $table->text('release_reason')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'academic_session_id', 'term_id']);
        });

        Schema::create('mark_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('mark_id')->constrained('marks')->cascadeOnDelete();
            $table->foreignId('marks_submission_id')->nullable()->constrained('marks_submissions')->nullOnDelete();
            $table->decimal('old_score', 5, 1)->nullable();
            $table->boolean('old_absent')->default(false);
            $table->decimal('new_score', 5, 1)->nullable();
            $table->boolean('new_absent')->default(false);
            $table->text('reason');
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('marks_entry_deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('sequence_id')->constrained('sequences')->cascadeOnDelete();
            $table->date('closes_on');
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'academic_session_id', 'sequence_id'], 'marks_deadline_unique');
        });

        if (! Schema::hasTable('permissions')) {
            return;
        }

        $holders = DB::table('role_permission')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('permissions.name', 'exam-publishing.publish')
            ->pluck('role_permission.role_id')
            ->unique();

        foreach ($this->permissions as $name => $label) {
            $id = DB::table('permissions')->where('name', $name)->value('id')
                ?? DB::table('permissions')->insertGetId([
                    'name' => $name, 'display_name' => $label, 'group_name' => 'Results Control',
                    'created_at' => now(), 'updated_at' => now(),
                ]);

            foreach ($holders as $roleId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id' => $roleId, 'permission_id' => $id, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->whereIn('name', array_keys($this->permissions))->delete();
        }
        Schema::dropIfExists('marks_entry_deadlines');
        Schema::dropIfExists('mark_corrections');
        Schema::dropIfExists('result_holds');
    }
};
