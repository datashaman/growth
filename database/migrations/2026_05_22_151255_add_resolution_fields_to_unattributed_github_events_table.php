<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unattributed_github_events', function (Blueprint $table) {
            $table->timestamp('resolved_at')->nullable()->after('received_at');
            $table->foreignId('resolved_by_user_id')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable()->after('resolved_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('unattributed_github_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resolved_by_user_id');
            $table->dropColumn(['resolved_at', 'resolution_note']);
        });
    }
};
