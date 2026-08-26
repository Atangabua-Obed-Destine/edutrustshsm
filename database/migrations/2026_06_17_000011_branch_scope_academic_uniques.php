<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make globally-unique reference columns unique PER BRANCH instead, so two
     * branches can each have e.g. session "2024/2025" or subject code "MATH".
     */
    public function up(): void
    {
        $this->swap('academic_sessions', 'name', ['branch_id', 'name']);
        $this->swap('streams', 'code', ['branch_id', 'code']);
        $this->swap('departments', 'name', ['branch_id', 'name']);
        $this->swap('subjects', 'code', ['branch_id', 'code']);
    }

    public function down(): void
    {
        $this->restore('academic_sessions', 'name', ['branch_id', 'name']);
        $this->restore('streams', 'code', ['branch_id', 'code']);
        $this->restore('departments', 'name', ['branch_id', 'name']);
        $this->restore('subjects', 'code', ['branch_id', 'code']);
    }

    private function swap(string $table, string $col, array $composite): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id')) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($col, $composite) {
            $t->dropUnique([$col]);
            $t->unique($composite);
        });
    }

    private function restore(string $table, string $col, array $composite): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($col, $composite) {
            $t->dropUnique($composite);
            $t->unique([$col]);
        });
    }
};
