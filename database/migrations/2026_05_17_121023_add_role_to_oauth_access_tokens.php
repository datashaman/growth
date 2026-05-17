<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The operating role a session is bound to (#183). Carried on the access
     * token the same way `workspace_id` is — null means an unbound session.
     */
    public function up(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table): void {
            if (! Schema::hasColumn('oauth_access_tokens', 'role')) {
                $table->string('role', 32)->nullable()->after('workspace_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table): void {
            if (Schema::hasColumn('oauth_access_tokens', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
