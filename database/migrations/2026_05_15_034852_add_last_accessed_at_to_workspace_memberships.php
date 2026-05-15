<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_memberships', function (Blueprint $table) {
            $table->timestamp('last_accessed_at')->nullable()->after('role');
        });

        DB::table('workspace_memberships')->update(['last_accessed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('workspace_memberships', function (Blueprint $table) {
            $table->dropColumn('last_accessed_at');
        });
    }
};
