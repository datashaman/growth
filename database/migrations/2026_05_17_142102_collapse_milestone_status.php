<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });

        $this->dropStatusCheckConstraint();

        DB::table('milestones')->where('status', 'hit')->update(['status' => 'achieved']);
        DB::table('milestones')->whereIn('status', ['missed', 'deferred'])->update(['status' => 'pending']);

        Schema::table('milestones', function (Blueprint $table) {
            $table->enum('status', ['pending', 'achieved'])->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });

        $this->dropStatusCheckConstraint();

        DB::table('milestones')->where('status', 'achieved')->update(['status' => 'hit']);

        Schema::table('milestones', function (Blueprint $table) {
            $table->enum('status', ['pending', 'hit', 'missed', 'deferred'])->default('pending')->change();
        });
    }

    /**
     * On PostgreSQL an enum column carries a named CHECK constraint that the
     * `string()->change()` above leaves in place, so the existing rows cannot
     * be rewritten to a value the old constraint forbids. Drop it explicitly
     * before the data migration; the later `enum()->change()` recreates it for
     * the new value set. SQLite rebuilds the table on `change()`, so it has no
     * lingering constraint to drop.
     */
    private function dropStatusCheckConstraint(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE milestones DROP CONSTRAINT IF EXISTS milestones_status_check');
        }
    }
};
