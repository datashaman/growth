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
        Schema::create('anomaly_requirement', function (Blueprint $table) {
            $table->foreignUlid('anomaly_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('requirement_id')->constrained()->cascadeOnDelete();
            $table->primary(['anomaly_id', 'requirement_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anomaly_requirement');
    }
};
