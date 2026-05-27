<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignUlid('superseded_by_project_id')->nullable()->after('status')->constrained('projects')->nullOnDelete();
            $table->foreignId('superseded_by_user_id')->nullable()->after('superseded_by_project_id')->constrained('users')->nullOnDelete();
            $table->timestamp('superseded_at')->nullable()->after('superseded_by_user_id');
            $table->text('supersession_reason')->nullable()->after('superseded_at');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('superseded_by_project_id');
            $table->dropConstrainedForeignId('superseded_by_user_id');
            $table->dropColumn(['superseded_at', 'supersession_reason']);
        });
    }
};
