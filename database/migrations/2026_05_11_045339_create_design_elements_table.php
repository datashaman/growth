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
        Schema::create('design_elements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('design_view_id')->constrained()->cascadeOnDelete();
            $table->enum('kind', ['entity', 'relationship', 'attribute', 'constraint']);
            $table->string('name');
            $table->string('type')->nullable();
            $table->text('purpose')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->index(['design_view_id', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('design_elements');
    }
};
