<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_feedback', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 32);
            $table->string('status', 16)->index();
            $table->string('tool_name', 120)->nullable()->index();
            $table->string('summary', 255);
            $table->text('body');
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
            $table->index(['category', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_feedback');
    }
};
