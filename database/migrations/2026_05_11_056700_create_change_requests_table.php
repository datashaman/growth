<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('requester_role_id')->nullable()
                ->constrained('roles')->nullOnDelete();
            $table->foreignUlid('review_id')->nullable()
                ->constrained('reviews')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('rationale')->nullable();
            $table->enum('category', ['scope', 'requirements', 'design', 'test', 'plan', 'risk', 'defect', 'compliance', 'other']);
            $table->enum('status', ['proposed', 'under_review', 'approved', 'rejected', 'deferred', 'implemented', 'cancelled'])
                ->default('proposed');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('decision', ['approved', 'rejected', 'deferred'])->nullable();
            $table->text('decision_rationale')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_requests');
    }
};
