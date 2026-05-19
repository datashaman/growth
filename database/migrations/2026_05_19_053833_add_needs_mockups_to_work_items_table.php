<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks a work item as one that requires one or more spec mockups before
     * it can be considered ready (#284).
     */
    public function up(): void
    {
        Schema::table('work_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('work_items', 'needs_mockups')) {
                $table->boolean('needs_mockups')->default(false)->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_items', function (Blueprint $table): void {
            if (Schema::hasColumn('work_items', 'needs_mockups')) {
                $table->dropColumn('needs_mockups');
            }
        });
    }
};
