<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_impacts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('change_request_id')->constrained()->cascadeOnDelete();
            $table->string('impactable_type');
            $table->string('impactable_id');
            $table->enum('impact_kind', ['creates', 'modifies', 'replaces', 'deprecates', 'removes', 'needs_analysis']);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['impactable_type', 'impactable_id']);
            $table->unique(['change_request_id', 'impactable_type', 'impactable_id'], 'change_impacts_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_impacts');
    }
};
