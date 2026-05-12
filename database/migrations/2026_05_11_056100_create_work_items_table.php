<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('parent_id')->nullable();
            $table->foreignUlid('responsible_role_id')->nullable()
                ->constrained('roles')->nullOnDelete();
            $table->enum('kind', ['deliverable', 'work_package', 'task']);
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['todo', 'in_progress', 'blocked', 'done', 'cancelled'])
                ->default('todo');
            $table->string('effort_estimate')->nullable();
            $table->string('effort_actual')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'parent_id']);
            $table->index(['project_id', 'status']);
        });

        Schema::table('work_items', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('work_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_items');
    }
};
