<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The persona a project Role carries (#314) — the instruction set served
     * to a session that adopts the Role: what it is accountable for, the
     * judgement it brings, what it must not do, and which operations are
     * routine for it versus warrant user confirmation. Distinct from
     * `responsibilities`; null means the Role serves no instructions.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->text('persona')->nullable()->after('responsibilities');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn('persona');
        });
    }
};
