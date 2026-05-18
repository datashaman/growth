<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A spec mockup — agent-authored HTML expressing a UI idea — stored
     * against a work item. The tracer slice keeps one mockup per work item;
     * naming and revisions arrive in later slices, so a `name` column lands
     * now to spare a reshape.
     */
    public function up(): void
    {
        Schema::create('spec_mockups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('work_item_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->longText('html');
            $table->timestamps();
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
