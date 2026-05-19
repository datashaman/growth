<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename the session-binding columns from "role" to "surface" (#318): the
     * bound value is a capability surface, not a role. `acting_role` becomes
     * `acting_surface` on the three audit tables, and the access-token `role`
     * column becomes `surface`. `renameColumn()` is portable across the
     * Postgres (CI) and SQLite (local) drivers.
     *
     * Notification payloads carry the same value as an `acting_role` key
     * inside the `data` JSON, which a column rename does not touch — so the
     * existing rows are backfilled here too, keeping a full cutover with no
     * read-time fallback shim.
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

        $this->renameNotificationDataKey('acting_role', 'acting_surface');
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

        $this->renameNotificationDataKey('acting_surface', 'acting_role');
    }

    /**
     * Rewrite a single key inside every notification's `data` JSON. Done
     * row-by-row in PHP so it is portable across the Postgres and SQLite
     * drivers, which expose incompatible JSON manipulation functions.
     */
    private function renameNotificationDataKey(string $from, string $to): void
    {
        DB::table('notifications')->orderBy('id')->lazy()->each(function (object $row) use ($from, $to): void {
            $data = json_decode((string) $row->data, true);

            if (! is_array($data) || ! array_key_exists($from, $data)) {
                return;
            }

            $data[$to] = $data[$from];
            unset($data[$from]);

            DB::table('notifications')->where('id', $row->id)->update(['data' => json_encode($data)]);
        });
    }
};
