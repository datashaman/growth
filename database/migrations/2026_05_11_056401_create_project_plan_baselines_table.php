<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_plan_baselines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->timestamp('baselined_at');
            $table->foreignId('baselined_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignUlid('baselined_by_agent_id')->nullable()
                ->constrained('agents')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['project_plan_id', 'version']);
            $table->index('baselined_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_plan_baselines');
    }
};
