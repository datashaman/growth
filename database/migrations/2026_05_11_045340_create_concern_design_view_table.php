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
        Schema::create('concern_design_view', function (Blueprint $table) {
            $table->foreignUlid('concern_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('design_view_id')->constrained()->cascadeOnDelete();
            $table->primary(['concern_id', 'design_view_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('concern_design_view');
    }
};
