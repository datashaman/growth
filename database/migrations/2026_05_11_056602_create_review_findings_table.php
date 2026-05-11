<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_findings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('review_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('owner_role_id')->nullable()
                ->constrained('roles')->nullOnDelete();
            $table->string('reviewable_type')->nullable();
            $table->string('reviewable_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['open', 'dispositioned', 'resolved', 'accepted', 'closed'])
                ->default('open');
            $table->date('due_at')->nullable();
            $table->text('disposition')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['review_id', 'status']);
            $table->index(['reviewable_type', 'reviewable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_findings');
    }
};
