<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->unsignedInteger('number')->nullable()->after('id');
        });

        $this->backfillNumbers();

        Schema::table('work_items', function (Blueprint $table) {
            $table->unique(['project_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'number']);
            $table->dropColumn('number');
        });
    }

    /**
     * Assign each work item a per-project sequential number, ordered by
     * creation. Work items have never carried a reference prefix, so this
     * is a straight chronological sequence with no embedded value to reuse.
     */
    private function backfillNumbers(): void
    {
        $projectIds = DB::table('work_items')
            ->distinct()
            ->pluck('project_id');

        foreach ($projectIds as $projectId) {
            $ids = DB::table('work_items')
                ->where('project_id', $projectId)
                ->orderBy('created_at')
                ->orderBy('id')
                ->pluck('id');

            $number = 0;
            foreach ($ids as $id) {
                DB::table('work_items')
                    ->where('id', $id)
                    ->update(['number' => ++$number]);
            }
        }
    }
};
