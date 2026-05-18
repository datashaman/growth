<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A work item may now hold several named mockups — the layout
     * alternatives an agent offers. A mockup's name identifies it within its
     * work item, so the pair is unique.
     */
    public function up(): void
    {
        Schema::table('spec_mockups', function (Blueprint $table) {
            $table->unique(['work_item_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spec_mockups', function (Blueprint $table) {
            $table->dropUnique(['work_item_id', 'name']);
        });
    }
};
