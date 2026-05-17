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
        $this->remapStatus(
            from: ['hit' => 'achieved', 'missed' => 'pending', 'deferred' => 'pending'],
            allowed: ['pending', 'achieved'],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->remapStatus(
            from: ['achieved' => 'hit'],
            allowed: ['pending', 'hit', 'missed', 'deferred'],
        );
    }

    /**
     * Rewrite milestone statuses and reset the column's allowed-value set.
     *
     * The status column is an enum, which both drivers back with a CHECK
     * constraint. That constraint must come off before the rows can be
     * rewritten to a value the old set forbids, and a fresh one put back for
     * the new set. The two drivers need different handling:
     *
     * - SQLite has no stable constraint name; `change()` rebuilds the table,
     *   so widening to a string drops the old CHECK and `enum()` restores it.
     * - PostgreSQL keeps the named `milestones_status_check` through a
     *   `change()`, and its `enum()` change emits `ALTER COLUMN ... TYPE
     *   varchar CHECK (...)`, which is invalid SQL — so the constraint is
     *   dropped and re-added directly.
     *
     * @param  array<string,string>  $from  old status => new status
     * @param  list<string>  $allowed  the resulting allowed values
     */
    private function remapStatus(array $from, array $allowed): void
    {
        $pgsql = DB::getDriverName() === 'pgsql';

        if ($pgsql) {
            DB::statement('ALTER TABLE milestones DROP CONSTRAINT IF EXISTS milestones_status_check');
        } else {
            Schema::table('milestones', function (Blueprint $table): void {
                $table->string('status')->default('pending')->change();
            });
        }

        foreach ($from as $old => $new) {
            DB::table('milestones')->where('status', $old)->update(['status' => $new]);
        }

        if ($pgsql) {
            $values = implode(', ', array_map(fn (string $v): string => "'{$v}'", $allowed));
            DB::statement("ALTER TABLE milestones ADD CONSTRAINT milestones_status_check CHECK (status IN ({$values}))");
        } else {
            Schema::table('milestones', function (Blueprint $table) use ($allowed): void {
                $table->enum('status', $allowed)->default('pending')->change();
            });
        }
    }
};
