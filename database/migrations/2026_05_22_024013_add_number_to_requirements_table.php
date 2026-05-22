<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->unsignedInteger('number')->nullable()->after('id');
        });

        $this->backfillNumbers();

        Schema::table('requirements', function (Blueprint $table) {
            $table->unique(['project_id', 'doc', 'number']);
        });
    }

    public function down(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'doc', 'number']);
            $table->dropColumn('number');
        });
    }

    /**
     * Assign each requirement a per-(project, doc) sequential number, ordered
     * by creation. The number drives the human reference (e.g. "SRS-001"), so
     * each document tier counts independently from one.
     */
    private function backfillNumbers(): void
    {
        $groups = DB::table('requirements')
            ->select('project_id', 'doc')
            ->distinct()
            ->get();

        foreach ($groups as $group) {
            $ids = DB::table('requirements')
                ->where('project_id', $group->project_id)
                ->where('doc', $group->doc)
                ->orderBy('created_at')
                ->orderBy('id')
                ->pluck('id');

            $number = 0;
            foreach ($ids as $id) {
                DB::table('requirements')
                    ->where('id', $id)
                    ->update(['number' => ++$number]);
            }
        }
    }
};
