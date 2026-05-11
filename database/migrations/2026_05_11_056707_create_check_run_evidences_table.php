<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_run_evidences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('work_item_delivery_link_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->nullable();
            $table->string('name');
            $table->string('run_ref')->nullable();
            $table->enum('status', ['queued', 'in_progress', 'completed'])->default('queued');
            $table->enum('conclusion', ['success', 'failure', 'cancelled', 'skipped', 'neutral', 'timed_out', 'action_required'])->nullable();
            $table->string('url')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['work_item_delivery_link_id', 'provider', 'name'], 'check_run_evidence_unique');
            $table->index(['status', 'conclusion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_run_evidences');
    }
};
