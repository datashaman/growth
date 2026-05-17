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
        Schema::table('work_items', function (Blueprint $table) {
            $table->dropColumn([
                'effort_estimate',
                'effort_estimate_hours',
                'effort_actual',
                'effort_actual_hours',
                'cost_estimate',
                'cost_estimate_amount',
                'cost_actual',
                'cost_actual_amount',
                'cost_currency',
            ]);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn([
                'weekly_capacity_hours',
                'hourly_rate_amount',
                'rate_currency',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->string('effort_estimate')->nullable();
            $table->decimal('effort_estimate_hours', 10, 2)->nullable()->after('effort_estimate');
            $table->string('effort_actual')->nullable()->after('effort_estimate_hours');
            $table->decimal('effort_actual_hours', 10, 2)->nullable()->after('effort_actual');
            $table->string('cost_estimate')->nullable()->after('effort_actual_hours');
            $table->decimal('cost_estimate_amount', 12, 2)->nullable()->after('cost_estimate');
            $table->string('cost_actual')->nullable()->after('cost_estimate_amount');
            $table->decimal('cost_actual_amount', 12, 2)->nullable()->after('cost_actual');
            $table->string('cost_currency', 3)->nullable()->after('cost_actual_amount');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->decimal('weekly_capacity_hours', 8, 2)->nullable();
            $table->decimal('hourly_rate_amount', 12, 2)->nullable()->after('weekly_capacity_hours');
            $table->string('rate_currency', 3)->nullable()->after('hourly_rate_amount');
        });
    }
};
