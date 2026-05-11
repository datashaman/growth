<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('status', ['draft', 'baselined', 'active', 'closed'])->default('draft');
            $table->text('scope_summary')->nullable();
            $table->text('objectives')->nullable();
            $table->text('deliverables_summary')->nullable();
            $table->text('approach')->nullable();
            $table->text('organization_summary')->nullable();
            $table->text('assumptions')->nullable();
            $table->text('constraints')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_plans');
    }
};
