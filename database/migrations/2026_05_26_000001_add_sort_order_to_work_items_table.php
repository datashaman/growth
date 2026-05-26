<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_items', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->nullable()->after('number');
            $table->index(['project_id', 'parent_id', 'sort_order']);
        });

        DB::table('work_items')->update(['sort_order' => DB::raw('number')]);
    }

    public function down(): void
    {
        Schema::table('work_items', function (Blueprint $table): void {
            $table->dropIndex(['project_id', 'parent_id', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
