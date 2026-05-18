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
        Schema::table('project_plan_baselines', function (Blueprint $table) {
            // Distinguishes a brownfield reconstruction snapshot (`adoption`)
            // from an ordinary planned baseline (`planned`, the default).
            $table->string('kind', 16)->default('planned')->after('version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_plan_baselines', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
