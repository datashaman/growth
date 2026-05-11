<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('test_plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->enum('level', ['master', 'unit', 'integration', 'system', 'acceptance']);
            $table->string('name');
            $table->text('scope')->nullable();
            $table->text('approach')->nullable();
            $table->text('pass_fail_criteria')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_plans');
    }
};
