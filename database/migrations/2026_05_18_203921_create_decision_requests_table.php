<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('target_role_id')->constrained('roles')->cascadeOnDelete();
            $table->text('question');
            $table->string('status')->default('open');
            $table->timestamp('deadline')->nullable();
            $table->nullableUlidMorphs('subjectable');

            // The answer — all null until the request is answered. The chosen
            // option is a soft reference; the option may be removed only by
            // cascade when the whole request is deleted.
            $table->ulid('chosen_option_id')->nullable();
            $table->text('answer_rationale')->nullable();
            $table->foreignId('answered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('answered_at')->nullable();

            $table->timestamps();

            // The role queue, and the scan for overdue open requests.
            $table->index(['target_role_id', 'status']);
            $table->index(['status', 'deadline']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_requests');
    }
};
