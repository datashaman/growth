<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('owner_role_id')->nullable()
                ->constrained('roles')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('category', ['technical', 'schedule', 'cost', 'compliance', 'operational', 'external', 'other']);
            $table->enum('probability', ['low', 'medium', 'high']);
            $table->enum('impact', ['low', 'medium', 'high']);
            $table->enum('status', ['identified', 'assessed', 'mitigating', 'mitigated', 'accepted', 'realized', 'closed'])
                ->default('identified');
            $table->text('mitigation_plan')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risks');
    }
};
