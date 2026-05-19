<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The project Role a session had adopted when a call or transition was
     * recorded (#314) — the genuine accountability, beside the capability
     * surface (`acting_surface`) and the principal (`user_id`/`agent_id`).
     *
     * Stored as both a nullable FK (`acting_role_id`, nulled if the Role is
     * later deleted) and a frozen name snapshot (`acting_role_name`) so the
     * audit trail still reads correctly once the Role is gone. Null for a
     * session that has not adopted a Role.
     */
    public function up(): void
    {
        foreach (['tool_invocations', 'status_transitions', 'feedback_comments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignUlid('acting_role_id')->nullable()->after('acting_surface')->constrained('roles')->nullOnDelete();
                $table->string('acting_role_name')->nullable()->after('acting_role_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['tool_invocations', 'status_transitions', 'feedback_comments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('acting_role_id');
                $table->dropColumn('acting_role_name');
            });
        }
    }
};
