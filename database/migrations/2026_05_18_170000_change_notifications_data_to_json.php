<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The notification inbox filters on `data->workspace_id`. Laravel compiles
     * that to the `->>` JSON operator, which PostgreSQL only accepts against a
     * `json`/`jsonb` column — not the `text` the table shipped with. Promote
     * the column to `json` so the filter is valid on every supported driver.
     *
     * PostgreSQL needs an explicit `USING` cast for the type change; the other
     * drivers store JSON as text and accept the schema-builder change as-is.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE json USING data::json');

            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->json('data')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');

            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->text('data')->change();
        });
    }
};
