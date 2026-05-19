<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The agent a session is acting as (#295). Carried on the access token
     * the same way `workspace_id` and `role` are — null means the session is
     * a user acting directly, with no agent attribution.
     */
    public function up(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table): void {
            if (! Schema::hasColumn('oauth_access_tokens', 'agent_id')) {
                $table->string('agent_id', 26)->nullable()->after('role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table): void {
            if (Schema::hasColumn('oauth_access_tokens', 'agent_id')) {
                $table->dropColumn('agent_id');
            }
        });
    }
};
