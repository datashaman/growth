<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milestones', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('target_date')->nullable();
            $table->text('exit_criteria')->nullable();
            $table->enum('status', ['pending', 'hit', 'missed', 'deferred'])->default('pending');
            $table->timestamps();

            $table->index(['project_id', 'target_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestones');
    }
};
