<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'target_date']);
            $table->dropColumn('target_date');
        });

        Schema::table('work_items', function (Blueprint $table) {
            $table->dropColumn(['planned_start_date', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->date('target_date')->nullable();
            $table->index(['project_id', 'target_date']);
        });

        Schema::table('work_items', function (Blueprint $table) {
            $table->date('planned_start_date')->nullable()->after('status');
            $table->date('due_date')->nullable()->after('planned_start_date');
        });
    }
};
