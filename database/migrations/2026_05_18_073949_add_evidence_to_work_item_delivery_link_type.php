<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen the delivery-link type enum to admit `evidence` links — the
     * visual-evidence galleries growth-sync cites on a work item.
     */
    public function up(): void
    {
        Schema::table('work_item_delivery_links', function (Blueprint $table) {
            $table->enum('type', ['commit', 'pull_request', 'branch', 'evidence'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_item_delivery_links', function (Blueprint $table) {
            $table->enum('type', ['commit', 'pull_request', 'branch'])->change();
        });
    }
};
