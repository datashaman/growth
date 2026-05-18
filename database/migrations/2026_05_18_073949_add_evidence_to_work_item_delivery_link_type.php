<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen the delivery-link type enum to admit `evidence` links — the
     * visual-evidence galleries growth-sync cites on a work item.
     */
    public function up(): void
    {
        $this->resetTypeConstraint(['commit', 'pull_request', 'branch', 'evidence']);
    }

    /**
     * Drop any `evidence` delivery links, then narrow the enum back.
     */
    public function down(): void
    {
        DB::table('work_item_delivery_links')->where('type', 'evidence')->delete();

        $this->resetTypeConstraint(['commit', 'pull_request', 'branch']);
    }

    /**
     * Reset the `type` column's allowed-value set.
     *
     * The column is an enum, which both drivers back with a CHECK constraint.
     * SQLite has no stable constraint name; `change()` rebuilds the table, so
     * `enum()` restores the CHECK for the new set. PostgreSQL keeps the named
     * `work_item_delivery_links_type_check` through a `change()`, and its
     * `enum()` change emits `ALTER COLUMN ... TYPE varchar CHECK (...)`, which
     * is invalid SQL — so the constraint is dropped and re-added directly.
     *
     * @param  list<string>  $allowed  the resulting allowed values
     */
    private function resetTypeConstraint(array $allowed): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE work_item_delivery_links DROP CONSTRAINT IF EXISTS work_item_delivery_links_type_check');
            $values = implode(', ', array_map(fn (string $v): string => "'{$v}'", $allowed));
            DB::statement("ALTER TABLE work_item_delivery_links ADD CONSTRAINT work_item_delivery_links_type_check CHECK (type IN ({$values}))");

            return;
        }

        Schema::table('work_item_delivery_links', function (Blueprint $table) use ($allowed): void {
            $table->enum('type', $allowed)->change();
        });
    }
};
