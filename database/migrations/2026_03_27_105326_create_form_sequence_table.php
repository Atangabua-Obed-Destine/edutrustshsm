<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_sequence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('stream_id')->nullable()->constrained('streams')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->foreignId('sequence_id')->constrained('sequences')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['form_id', 'stream_id', 'term_id', 'sequence_id'], 'form_seq_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_sequence');
    }
};
