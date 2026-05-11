<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('owner_role_id')->nullable()
                ->constrained('roles')->nullOnDelete();
            $table->enum('type', ['management_review', 'technical_review', 'inspection', 'walkthrough', 'audit']);
            $table->string('title');
            $table->text('objective')->nullable();
            $table->enum('status', ['planned', 'in_progress', 'held', 'closed', 'cancelled'])
                ->default('planned');
            $table->timestamp('planned_at')->nullable();
            $table->timestamp('held_at')->nullable();
            $table->json('entry_criteria')->nullable();
            $table->json('exit_criteria')->nullable();
            $table->enum('decision', ['accepted', 'accepted_with_actions', 'rework_required', 'rejected', 'deferred'])
                ->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
