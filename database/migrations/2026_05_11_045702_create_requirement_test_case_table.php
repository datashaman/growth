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
        Schema::create('requirement_test_case', function (Blueprint $table) {
            $table->foreignUlid('requirement_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('test_case_id')->constrained()->cascadeOnDelete();
            $table->primary(['requirement_id', 'test_case_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requirement_test_case');
    }
};
