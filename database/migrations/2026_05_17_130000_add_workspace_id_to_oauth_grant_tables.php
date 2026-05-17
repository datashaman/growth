<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Carry the workspace binding through the OAuth grant chain so a token
     * issued via the consent screen is workspace-bound (#197, #214).
     *
     * The auth code receives the binding the user picked on the consent
     * screen; the access token copies it off the grant it consumes. The
     * refresh token carries it forward so a refreshed token keeps the same
     * workspace — symmetric with how `oauth_access_tokens.workspace_id` works.
     */
    public function up(): void
    {
        Schema::table('oauth_auth_codes', function (Blueprint $table): void {
            if (! Schema::hasColumn('oauth_auth_codes', 'workspace_id')) {
                $table->foreignUlid('workspace_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            }
        });

        Schema::table('oauth_refresh_tokens', function (Blueprint $table): void {
            if (! Schema::hasColumn('oauth_refresh_tokens', 'workspace_id')) {
                $table->foreignUlid('workspace_id')->nullable()->after('access_token_id')->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('oauth_auth_codes', function (Blueprint $table): void {
            if (Schema::hasColumn('oauth_auth_codes', 'workspace_id')) {
                $table->dropConstrainedForeignId('workspace_id');
            }
        });

        Schema::table('oauth_refresh_tokens', function (Blueprint $table): void {
            if (Schema::hasColumn('oauth_refresh_tokens', 'workspace_id')) {
                $table->dropConstrainedForeignId('workspace_id');
            }
        });
    }
};
