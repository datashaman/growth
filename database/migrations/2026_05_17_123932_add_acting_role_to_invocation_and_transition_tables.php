<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The operating role a session was bound to when a call or transition was
     * made (#190) — the capacity, beside the existing who (`user_id`) and what.
     * Null for an unbound session.
     */
    public function up(): void
    {
        Schema::table('tool_invocations', function (Blueprint $table): void {
            if (! Schema::hasColumn('tool_invocations', 'acting_role')) {
                $table->string('acting_role', 32)->nullable()->after('user_id');
            }
        });

        Schema::table('status_transitions', function (Blueprint $table): void {
            if (! Schema::hasColumn('status_transitions', 'acting_role')) {
                $table->string('acting_role', 32)->nullable()->after('transitioned_by_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tool_invocations', function (Blueprint $table): void {
            if (Schema::hasColumn('tool_invocations', 'acting_role')) {
                $table->dropColumn('acting_role');
            }
        });

        Schema::table('status_transitions', function (Blueprint $table): void {
            if (Schema::hasColumn('status_transitions', 'acting_role')) {
                $table->dropColumn('acting_role');
            }
        });
    }
};
