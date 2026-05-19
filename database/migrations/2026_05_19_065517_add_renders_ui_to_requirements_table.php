<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            // Explicit flag: this requirement renders UI, so it is not fully
            // verified until a passing run carries visual evidence (#245).
            $table->boolean('renders_ui')->default(false)->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->dropColumn('renders_ui');
        });
    }
};
