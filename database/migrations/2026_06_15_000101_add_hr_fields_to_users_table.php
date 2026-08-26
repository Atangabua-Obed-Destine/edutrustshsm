<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Enrich the User model into the HR staff record (employment + payroll). */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Identity
            $table->string('staff_id')->nullable()->unique()->after('id');
            $table->string('father_name')->nullable()->after('other_names');
            $table->string('mother_name')->nullable()->after('father_name');
            $table->date('date_of_birth')->nullable()->after('mother_name');
            $table->string('nationality')->nullable()->after('date_of_birth');
            $table->string('religion')->nullable()->after('nationality');
            $table->string('marital_status')->nullable()->after('religion');
            $table->string('blood_group')->nullable()->after('marital_status');
            $table->string('national_id')->nullable()->after('blood_group');
            $table->string('passport_no')->nullable()->after('national_id');
            $table->string('emergency_phone')->nullable()->after('phone');
            $table->text('present_address')->nullable()->after('emergency_phone');
            $table->text('permanent_address')->nullable()->after('present_address');

            // Employment
            $table->foreignId('department_id')->nullable()->after('role')->constrained('departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->after('department_id')->constrained('designations')->nullOnDelete();
            $table->foreignId('work_shift_id')->nullable()->after('designation_id')->constrained('work_shift_types')->nullOnDelete();
            $table->string('contract_type')->nullable()->after('work_shift_id'); // Full Time / Part Time
            $table->unsignedTinyInteger('salary_type')->nullable()->after('contract_type'); // 1=fixed, 2=hourly
            $table->decimal('basic_salary', 15, 2)->nullable()->after('salary_type');
            $table->date('joining_date')->nullable()->after('basic_salary');
            $table->date('ending_date')->nullable()->after('joining_date');

            // Education
            $table->string('education_level')->nullable()->after('ending_date');
            $table->string('academy')->nullable()->after('education_level');
            $table->string('graduation_year')->nullable()->after('academy');
            $table->string('graduation_field')->nullable()->after('graduation_year');
            $table->text('experience')->nullable()->after('graduation_field');

            // Tax / media
            $table->string('tin_no')->nullable()->after('experience');
            $table->string('signature')->nullable()->after('tin_no');
            $table->string('resume')->nullable()->after('signature');
            $table->string('joining_letter')->nullable()->after('resume');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('designation_id');
            $table->dropConstrainedForeignId('work_shift_id');
            $table->dropColumn([
                'staff_id', 'father_name', 'mother_name', 'date_of_birth', 'nationality',
                'religion', 'marital_status', 'blood_group', 'national_id', 'passport_no',
                'emergency_phone', 'present_address', 'permanent_address', 'contract_type',
                'salary_type', 'basic_salary', 'joining_date', 'ending_date',
                'education_level', 'academy', 'graduation_year', 'graduation_field',
                'experience', 'tin_no', 'signature', 'resume', 'joining_letter',
            ]);
        });
    }
};
