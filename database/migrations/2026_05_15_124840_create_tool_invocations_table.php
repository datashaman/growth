<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_invocations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tool_name', 120)->index();
            $table->string('transport', 16)->nullable();
            $table->boolean('success')->index();
            $table->string('error_class', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms');
            $table->json('args_shape')->nullable();
            $table->json('return_shape')->nullable();
            $table->json('args_full')->nullable();
            $table->json('return_full')->nullable();
            $table->timestamp('started_at')->index();
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->index(['workspace_id', 'started_at']);
            $table->index(['tool_name', 'success', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_invocations');
    }
};
