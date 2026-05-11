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
        Schema::create('test_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('test_case_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pass', 'fail', 'blocked', 'skipped']);
            $table->dateTime('run_at');
            $table->text('notes')->nullable();
            $table->json('environment_snapshot')->nullable();
            $table->timestamps();

            $table->index(['test_case_id', 'status']);
            $table->index('run_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_runs');
    }
};
