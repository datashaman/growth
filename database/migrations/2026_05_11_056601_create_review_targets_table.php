<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_targets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_id')->constrained()->cascadeOnDelete();
            $table->string('reviewable_type');
            $table->string('reviewable_id');
            $table->string('context')->nullable();
            $table->timestamps();

            $table->index(['reviewable_type', 'reviewable_id']);
            $table->unique(['review_id', 'reviewable_type', 'reviewable_id'], 'review_targets_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_targets');
    }
};
