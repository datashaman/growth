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
        Schema::create('test_cases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('test_plan_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('objective')->nullable();
            $table->json('preconditions')->nullable();
            $table->json('inputs')->nullable();
            $table->text('expected_results');
            $table->text('environment')->nullable();
            $table->timestamps();

            $table->index('test_plan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_cases');
    }
};
