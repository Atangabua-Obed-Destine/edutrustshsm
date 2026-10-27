<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A child can have more than one parent.
 *
 * `students.guardian_id` allowed exactly one, so a separated couple, or a
 * father who pays the fees while an aunt is the daily contact, could not both
 * exist — and only one of them could ever see the child in the portal.
 *
 * The column stays, and now means one specific thing: the primary guardian,
 * the one the school writes to first. The pivot is the full set of links and
 * is what decides who may see a child. The service layer keeps the two in
 * step so nothing reading `guardian_id` has to change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('guardian_id')->constrained('guardians')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            // Who this adult is to the child, in their own words. Not an enum:
            // "grandmother", "elder brother" and "sponsor" are all real answers
            // a school gives, and refusing them would push them into "other".
            $table->string('relationship', 60)->nullable();

            // Exactly one link per child carries this, mirroring students.guardian_id.
            $table->boolean('is_primary')->default(false);

            // A parent may be reachable but not entitled to the portal — a
            // sponsor who pays, for instance, with no right to school reports.
            $table->boolean('can_access_portal')->default(true);

            $table->timestamps();

            $table->unique(['guardian_id', 'student_id'], 'guardian_student_unique');
            $table->index(['student_id', 'is_primary'], 'guardian_student_primary_index');
        });

        $this->backfill();
    }

    /**
     * Every existing link becomes a primary one.
     *
     * Done in chunks rather than one insert: a school with thousands of pupils
     * would otherwise build the whole payload in memory.
     */
    private function backfill(): void
    {
        DB::table('students')
            ->whereNotNull('guardian_id')
            ->orderBy('id')
            ->chunk(500, function ($students) {
                $now = now();
                $rows = [];

                foreach ($students as $student) {
                    $rows[] = [
                        'branch_id' => $student->branch_id,
                        'guardian_id' => $student->guardian_id,
                        'student_id' => $student->id,
                        // Unknown at this point — the old column said who, never what.
                        'relationship' => null,
                        'is_primary' => true,
                        'can_access_portal' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows) {
                    DB::table('guardian_student')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_student');
    }
};
