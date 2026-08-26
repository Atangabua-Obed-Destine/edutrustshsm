<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\FeeCategory;
use App\Models\Form;
use App\Models\GradeScale;
use App\Models\SchoolSetting;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultDataSeeder extends Seeder
{
    public function run(): void
    {
        // School Settings
        SchoolSetting::create([
            'school_name' => 'LONGLA COMPREHENSIVE COLLEGE BAMENDA',
            'school_short_name' => 'LCC Bamenda',
            'school_code' => 'LCC',
            'address' => 'Bamenda',
            'city' => 'Bamenda',
            'region' => 'North West Region',
            'phone' => '+237 000 000 000',
            'email' => 'info@longlacc.com',
            'motto' => 'Knowledge, Discipline, Excellence',
            'student_id_prefix' => 'LCC',
            'receipt_prefix' => 'RCP',
            'max_terms_per_session' => 3,
            'max_sequences_per_term' => 2,
            'pass_mark' => 10.00,
            'promotion_threshold' => 10.00,
            'min_attendance_percent' => 80,
            'max_mark' => 20.00,
            'currency' => 'XAF',
        ]);

        // Super Admin
        User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin@edutrustschool.local',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'gender' => 'male',
            'is_active' => true,
        ]);

        // Secondary forms (Form 1-5 = First Cycle, Lower/Upper Sixth = Second Cycle)
        $forms = [
            ['name' => 'Form 1', 'short_name' => 'F1', 'school_level' => 'secondary', 'level' => 'first_cycle', 'education_system' => 'english', 'display_order' => 1, 'has_streams' => true],
            ['name' => 'Form 2', 'short_name' => 'F2', 'school_level' => 'secondary', 'level' => 'first_cycle', 'education_system' => 'english', 'display_order' => 2, 'has_streams' => true],
            ['name' => 'Form 3', 'short_name' => 'F3', 'school_level' => 'secondary', 'level' => 'first_cycle', 'education_system' => 'english', 'display_order' => 3, 'has_streams' => true],
            ['name' => 'Form 4', 'short_name' => 'F4', 'school_level' => 'secondary', 'level' => 'first_cycle', 'education_system' => 'english', 'display_order' => 4, 'has_streams' => true],
            ['name' => 'Form 5', 'short_name' => 'F5', 'school_level' => 'secondary', 'level' => 'first_cycle', 'education_system' => 'english', 'display_order' => 5, 'has_streams' => true],
            ['name' => 'Lower Sixth', 'short_name' => 'LS', 'school_level' => 'secondary', 'level' => 'second_cycle', 'education_system' => 'english', 'display_order' => 6, 'has_streams' => true],
            ['name' => 'Upper Sixth', 'short_name' => 'US', 'school_level' => 'secondary', 'level' => 'second_cycle', 'education_system' => 'english', 'display_order' => 7, 'has_streams' => true],

            // Nursery & Primary classes (no streams)
            ['name' => 'Nursery 1', 'short_name' => 'N1', 'school_level' => 'nursery_primary', 'level' => 'nursery', 'education_system' => 'english', 'display_order' => 1, 'has_streams' => false],
            ['name' => 'Nursery 2', 'short_name' => 'N2', 'school_level' => 'nursery_primary', 'level' => 'nursery', 'education_system' => 'english', 'display_order' => 2, 'has_streams' => false],
            ['name' => 'Nursery 3', 'short_name' => 'N3', 'school_level' => 'nursery_primary', 'level' => 'nursery', 'education_system' => 'english', 'display_order' => 3, 'has_streams' => false],
            ['name' => 'Class 1', 'short_name' => 'C1', 'school_level' => 'nursery_primary', 'level' => 'primary', 'education_system' => 'english', 'display_order' => 4, 'has_streams' => false],
            ['name' => 'Class 2', 'short_name' => 'C2', 'school_level' => 'nursery_primary', 'level' => 'primary', 'education_system' => 'english', 'display_order' => 5, 'has_streams' => false],
            ['name' => 'Class 3', 'short_name' => 'C3', 'school_level' => 'nursery_primary', 'level' => 'primary', 'education_system' => 'english', 'display_order' => 6, 'has_streams' => false],
            ['name' => 'Class 4', 'short_name' => 'C4', 'school_level' => 'nursery_primary', 'level' => 'primary', 'education_system' => 'english', 'display_order' => 7, 'has_streams' => false],
            ['name' => 'Class 5', 'short_name' => 'C5', 'school_level' => 'nursery_primary', 'level' => 'primary', 'education_system' => 'english', 'display_order' => 8, 'has_streams' => false],
            ['name' => 'Class 6', 'short_name' => 'C6', 'school_level' => 'nursery_primary', 'level' => 'primary', 'education_system' => 'english', 'display_order' => 9, 'has_streams' => false],
        ];

        foreach ($forms as $form) {
            Form::create($form);
        }

        // Streams
        Stream::create(['name' => 'General', 'code' => 'GEN', 'is_general' => true]);
        $science = Stream::create(['name' => 'Science', 'code' => 'SCI']);
        $arts = Stream::create(['name' => 'Arts', 'code' => 'ART']);
        Stream::create(['name' => 'Commercial', 'code' => 'COM']);

        // Attach streams only to forms that use streams (secondary); nursery/primary have none
        $allStreams = Stream::all();
        foreach (Form::where('has_streams', true)->get() as $form) {
            $form->streams()->attach($allStreams);
        }

        // Departments
        $departmentNames = [
            'Languages', 'Sciences', 'Mathematics', 'Social Sciences',
            'Commercial Studies', 'Technical Studies', 'Physical Education', 'Religious Studies',
        ];

        $deptModels = [];
        foreach ($departmentNames as $name) {
            $deptModels[$name] = Department::create(['name' => $name]);
        }

        // Subjects (Cameroon Anglophone curriculum)
        $subjects = [
            // Languages
            ['name' => 'English Language', 'code' => 'ENG', 'department_id' => $deptModels['Languages']->id],
            ['name' => 'French', 'code' => 'FRE', 'department_id' => $deptModels['Languages']->id],
            ['name' => 'Literature in English', 'code' => 'LIT', 'department_id' => $deptModels['Languages']->id],
            ['name' => 'Spanish', 'code' => 'SPA', 'department_id' => $deptModels['Languages']->id],
            ['name' => 'German', 'code' => 'GER', 'department_id' => $deptModels['Languages']->id],

            // Sciences
            ['name' => 'Mathematics', 'code' => 'MAT', 'department_id' => $deptModels['Mathematics']->id],
            ['name' => 'Additional Mathematics', 'code' => 'ADD', 'department_id' => $deptModels['Mathematics']->id],
            ['name' => 'Physics', 'code' => 'PHY', 'department_id' => $deptModels['Sciences']->id],
            ['name' => 'Chemistry', 'code' => 'CHE', 'department_id' => $deptModels['Sciences']->id],
            ['name' => 'Biology', 'code' => 'BIO', 'department_id' => $deptModels['Sciences']->id],
            ['name' => 'Computer Science', 'code' => 'CSC', 'department_id' => $deptModels['Sciences']->id],
            ['name' => 'Further Mathematics', 'code' => 'FMA', 'department_id' => $deptModels['Mathematics']->id],

            // Social Sciences
            ['name' => 'History', 'code' => 'HIS', 'department_id' => $deptModels['Social Sciences']->id],
            ['name' => 'Geography', 'code' => 'GEO', 'department_id' => $deptModels['Social Sciences']->id],
            ['name' => 'Economics', 'code' => 'ECO', 'department_id' => $deptModels['Social Sciences']->id],
            ['name' => 'Citizenship Education', 'code' => 'CIT', 'department_id' => $deptModels['Social Sciences']->id],
            ['name' => 'Logic', 'code' => 'LOG', 'department_id' => $deptModels['Social Sciences']->id],
            ['name' => 'Philosophy', 'code' => 'PHI', 'department_id' => $deptModels['Social Sciences']->id],

            // Commercial Studies
            ['name' => 'Commerce', 'code' => 'COM', 'department_id' => $deptModels['Commercial Studies']->id],
            ['name' => 'Accounting', 'code' => 'ACC', 'department_id' => $deptModels['Commercial Studies']->id],
            ['name' => 'Business Studies', 'code' => 'BUS', 'department_id' => $deptModels['Commercial Studies']->id],
            ['name' => 'Financial Accounting', 'code' => 'FAC', 'department_id' => $deptModels['Commercial Studies']->id],
            ['name' => 'Management', 'code' => 'MGT', 'department_id' => $deptModels['Commercial Studies']->id],

            // Technical Studies
            ['name' => 'Food and Nutrition', 'code' => 'FNU', 'department_id' => $deptModels['Technical Studies']->id],
            ['name' => 'Home Economics', 'code' => 'HEC', 'department_id' => $deptModels['Technical Studies']->id],
            ['name' => 'Technical Drawing', 'code' => 'TDR', 'department_id' => $deptModels['Technical Studies']->id],
            ['name' => 'Woodwork', 'code' => 'WOD', 'department_id' => $deptModels['Technical Studies']->id],
            ['name' => 'Metalwork', 'code' => 'MET', 'department_id' => $deptModels['Technical Studies']->id],

            // Physical Education
            ['name' => 'Physical Education', 'code' => 'PHE', 'department_id' => $deptModels['Physical Education']->id],
            ['name' => 'Sports Science', 'code' => 'SSC', 'department_id' => $deptModels['Physical Education']->id],

            // Religious Studies
            ['name' => 'Christian Religious Studies', 'code' => 'CRS', 'department_id' => $deptModels['Religious Studies']->id],
            ['name' => 'Islamic Religious Studies', 'code' => 'IRS', 'department_id' => $deptModels['Religious Studies']->id],
            ['name' => 'Moral Education', 'code' => 'MOR', 'department_id' => $deptModels['Religious Studies']->id],
        ];

        foreach ($subjects as $subject) {
            Subject::create($subject);
        }

        // Grade Scale (Cameroon 0-20 system)
        $grades = [
            ['grade' => 'A+', 'min_mark' => 18.0, 'max_mark' => 20.0, 'description' => 'Excellent', 'display_order' => 1],
            ['grade' => 'A',  'min_mark' => 16.0, 'max_mark' => 17.9, 'description' => 'Very Good', 'display_order' => 2],
            ['grade' => 'B+', 'min_mark' => 15.0, 'max_mark' => 15.9, 'description' => 'Good', 'display_order' => 3],
            ['grade' => 'B',  'min_mark' => 14.0, 'max_mark' => 14.9, 'description' => 'Fairly Good', 'display_order' => 4],
            ['grade' => 'C+', 'min_mark' => 13.0, 'max_mark' => 13.9, 'description' => 'Satisfactory', 'display_order' => 5],
            ['grade' => 'C',  'min_mark' => 12.0, 'max_mark' => 12.9, 'description' => 'Average', 'display_order' => 6],
            ['grade' => 'D+', 'min_mark' => 11.0, 'max_mark' => 11.9, 'description' => 'Below Average', 'display_order' => 7],
            ['grade' => 'D',  'min_mark' => 10.0, 'max_mark' => 10.9, 'description' => 'Pass', 'display_order' => 8],
            ['grade' => 'E',  'min_mark' => 8.0,  'max_mark' => 9.9,  'description' => 'Poor', 'display_order' => 9],
            ['grade' => 'F',  'min_mark' => 0.0,  'max_mark' => 7.9,  'description' => 'Fail', 'display_order' => 10],
        ];

        foreach ($grades as $grade) {
            GradeScale::create($grade);
        }

        // Fee Categories
        $feeCategories = [
            ['name' => 'Tuition Fee', 'code' => 'TUI', 'is_mandatory' => true, 'is_refundable' => false],
            ['name' => 'Registration Fee', 'code' => 'REG', 'is_mandatory' => true, 'is_refundable' => false],
            ['name' => 'PTA Levy', 'code' => 'PTA', 'is_mandatory' => true, 'is_refundable' => false],
            ['name' => 'Sports Fee', 'code' => 'SPT', 'is_mandatory' => true, 'is_refundable' => false],
            ['name' => 'Library Fee', 'code' => 'LIB', 'is_mandatory' => true, 'is_refundable' => false],
            ['name' => 'Lab Fee', 'code' => 'LAB', 'is_mandatory' => false, 'is_refundable' => false],
            ['name' => 'Boarding Fee', 'code' => 'BRD', 'is_mandatory' => false, 'is_refundable' => true],
            ['name' => 'Exam Fee', 'code' => 'EXM', 'is_mandatory' => true, 'is_refundable' => false],
        ];

        foreach ($feeCategories as $cat) {
            FeeCategory::create($cat);
        }
    }
}
