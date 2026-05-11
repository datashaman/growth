<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('releases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->string('name')->nullable();
            $table->enum('status', ['planned', 'candidate', 'released', 'cancelled'])->default('planned');
            $table->timestamp('released_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'version']);
            $table->index(['project_id', 'status']);
        });

        Schema::create('release_work_item', function (Blueprint $table) {
            $table->foreignUlid('release_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('work_item_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['release_id', 'work_item_id']);
        });

        Schema::create('deployments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('release_id')->nullable()->constrained()->nullOnDelete();
            $table->string('environment');
            $table->enum('status', ['planned', 'in_progress', 'succeeded', 'failed', 'rolled_back', 'cancelled'])->default('planned');
            $table->timestamp('deployed_at')->nullable();
            $table->string('url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'environment', 'status']);
        });

        Schema::create('deployment_delivery_link', function (Blueprint $table) {
            $table->foreignUlid('deployment_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('work_item_delivery_link_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['deployment_id', 'work_item_delivery_link_id'], 'deployment_delivery_link_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_delivery_link');
        Schema::dropIfExists('deployments');
        Schema::dropIfExists('release_work_item');
        Schema::dropIfExists('releases');
    }
};
