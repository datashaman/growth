<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A spec mockup — agent-authored HTML expressing a UI idea — stored
     * against a work item. A mockup's name identifies it within its work
     * item, so the pair is unique: the tracer slice attaches one mockup,
     * later slices the named layout alternatives, with no reshape.
     */
    public function up(): void
    {
        Schema::create('spec_mockups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('work_item_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->longText('html');
            $table->timestamps();

            $table->unique(['work_item_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spec_mockups');
    }
};
