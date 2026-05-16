<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unattributed_github_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('github_repo');
            $table->string('event_type');
            $table->string('branch')->nullable();
            $table->string('commit_sha');
            $table->string('reason');
            $table->string('url')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();

            // One row per commit: a re-run or a second failing check on the
            // same commit overwrites rather than piling up.
            $table->unique(['github_repo', 'commit_sha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unattributed_github_events');
    }
};
