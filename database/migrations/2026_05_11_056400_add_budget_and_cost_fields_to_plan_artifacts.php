<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_plans', function (Blueprint $table) {
            $table->text('budget_summary')->nullable()->after('constraints');
        });

        Schema::table('work_items', function (Blueprint $table) {
            $table->string('cost_estimate')->nullable()->after('effort_actual');
            $table->string('cost_actual')->nullable()->after('cost_estimate');
        });
    }

    public function down(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->dropColumn(['cost_estimate', 'cost_actual']);
        });

        Schema::table('project_plans', function (Blueprint $table) {
            $table->dropColumn('budget_summary');
        });
    }
};
