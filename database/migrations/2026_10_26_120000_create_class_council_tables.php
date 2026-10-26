<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The class council — the meeting that turns a term's marks into decisions.
 *
 * The school already recorded a class teacher remark, a principal remark and
 * a conduct grade on each term result, but nothing recorded who decided them,
 * when, or what was awarded. A council sat and its outcome lived nowhere.
 *
 * The statistics are frozen onto the council when it concludes rather than
 * recomputed on every view. The minutes have to say what the meeting saw; a
 * mark corrected next week must not quietly rewrite what the council was told.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_councils', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('class_section_id')->constrained('class_sections')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->nullable()->constrained('academic_sessions')->nullOnDelete();

            // Staff refer to a council in writing, as they do a notice.
            $table->string('reference', 30)->nullable();
            $table->date('meeting_date')->nullable();
            $table->string('venue', 150)->nullable();
            $table->foreignId('chaired_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('secretary_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 20)->default('open'); // open | concluded
            $table->text('deliberations')->nullable();
            $table->text('action_items')->nullable();

            // Frozen at conclusion — what the meeting actually saw.
            $table->unsignedSmallInteger('students')->nullable();
            $table->unsignedSmallInteger('passed')->nullable();
            $table->unsignedSmallInteger('failed')->nullable();
            $table->decimal('class_average', 5, 2)->nullable();
            $table->decimal('pass_rate', 5, 2)->nullable();
            $table->decimal('highest_average', 5, 2)->nullable();
            $table->decimal('lowest_average', 5, 2)->nullable();

            $table->timestamp('concluded_at')->nullable();
            $table->foreignId('concluded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One council per class per term. A second sitting amends the
            // first rather than competing with it.
            $table->unique(['class_section_id', 'term_id'], 'council_class_term_unique');
            $table->unique(['branch_id', 'reference'], 'council_branch_reference_unique');
            $table->index(['branch_id', 'status'], 'council_branch_status_index');
        });

        Schema::create('class_council_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('class_council_id')->constrained('class_councils')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('term_result_id')->nullable()->constrained('term_results')->nullOnDelete();

            // Copied from the result at the time, for the same reason the
            // council statistics are frozen.
            $table->decimal('average', 5, 2)->nullable();
            $table->unsignedSmallInteger('rank')->nullable();

            $table->string('award', 30)->default('none');
            $table->string('conduct', 20)->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->unique(['class_council_id', 'student_id'], 'council_entry_student_unique');
        });

        // Who sat and signed. A decision nobody is named against is not a
        // decision the school can stand behind later.
        Schema::create('class_council_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('class_council_id')->constrained('class_councils')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 150);
            $table->string('role', 100)->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_council_signatures');
        Schema::dropIfExists('class_council_entries');
        Schema::dropIfExists('class_councils');
    }
};
