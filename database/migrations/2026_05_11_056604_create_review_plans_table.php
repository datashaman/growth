<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['management_review', 'technical_review', 'inspection', 'walkthrough', 'audit']);
            $table->string('name');
            $table->text('objective')->nullable();
            $table->text('procedure')->nullable();
            $table->json('entry_criteria')->nullable();
            $table->json('exit_criteria')->nullable();
            $table->json('expected_responsibilities')->nullable();
            $table->json('checklist')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_plans');
    }
};
