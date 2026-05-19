<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename the session-binding columns from "role" to "surface" (#318): the
     * bound value is a capability surface, not a role. `acting_role` becomes
     * `acting_surface` on the three audit tables, and the access-token `role`
     * column becomes `surface`. `renameColumn()` is portable across the
     * Postgres (CI) and SQLite (local) drivers.
     */
    public function up(): void
    {
        Schema::table('tool_invocations', function (Blueprint $table): void {
            $table->renameColumn('acting_role', 'acting_surface');
        });

        Schema::table('status_transitions', function (Blueprint $table): void {
            $table->renameColumn('acting_role', 'acting_surface');
        });

        Schema::table('feedback_comments', function (Blueprint $table): void {
            $table->renameColumn('acting_role', 'acting_surface');
        });

        Schema::table('oauth_access_tokens', function (Blueprint $table): void {
            $table->renameColumn('role', 'surface');
        });
    }

    public function down(): void
    {
        Schema::table('tool_invocations', function (Blueprint $table): void {
            $table->renameColumn('acting_surface', 'acting_role');
        });

        Schema::table('status_transitions', function (Blueprint $table): void {
            $table->renameColumn('acting_surface', 'acting_role');
        });

        Schema::table('feedback_comments', function (Blueprint $table): void {
            $table->renameColumn('acting_surface', 'acting_role');
        });

        Schema::table('oauth_access_tokens', function (Blueprint $table): void {
            $table->renameColumn('surface', 'role');
        });
    }
};
