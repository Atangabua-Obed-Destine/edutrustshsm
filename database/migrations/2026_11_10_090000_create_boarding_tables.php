<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Boarding: the houses (dormitories), their rooms and beds, and which boarder
 * sleeps where. Follows the reference system's hostel module, tied to what
 * EduTrust already knows: an enrolment says whether a pupil is a boarder, so
 * the house list can say who still needs a bed and who no longer should hold one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boarding_houses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name', 100);
            // boys, girls or mixed: a boarder is only placed in a house that takes them.
            $table->string('gender', 10)->default('mixed');
            $table->foreignId('house_master_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'name']);
        });

        Schema::create('boarding_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('boarding_house_id')->constrained('boarding_houses')->cascadeOnDelete();
            $table->string('name', 50);
            $table->unsignedSmallInteger('beds')->default(1);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['boarding_house_id', 'name']);
        });

        Schema::create('boarding_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            // A room that holds or held a boarder keeps its history: it cannot be deleted.
            $table->foreignId('boarding_room_id')->constrained('boarding_rooms')->restrictOnDelete();
            $table->string('bed', 20)->nullable();
            $table->date('start_date');
            // Open while the boarder is in the bed; closed when they move or leave.
            $table->date('end_date')->nullable();
            $table->string('end_reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['student_id', 'end_date']);
            $table->index(['boarding_room_id', 'end_date']);
        });

        // Viewing the houses, setting them up, and placing boarders are
        // separate acts: a house master places boarders without redrawing rooms.
        if (Schema::hasTable('permissions')) {
            $ids = [];
            foreach (['view' => 'View', 'manage' => 'Manage', 'allocate' => 'Allocate'] as $action => $label) {
                $name = 'boarding.'.$action;
                $ids[$action] = DB::table('permissions')->where('name', $name)->value('id')
                    ?? DB::table('permissions')->insertGetId([
                        'name' => $name, 'display_name' => $label, 'group_name' => 'Boarding',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
            }

            foreach (['super_admin', 'admin'] as $role) {
                $roleId = DB::table('roles')->where('name', $role)->value('id');
                foreach ($roleId ? $ids : [] as $permissionId) {
                    DB::table('role_permission')->insertOrIgnore([
                        'role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->where('group_name', 'Boarding')->delete();
        }
        Schema::dropIfExists('boarding_allocations');
        Schema::dropIfExists('boarding_rooms');
        Schema::dropIfExists('boarding_houses');
    }
};
