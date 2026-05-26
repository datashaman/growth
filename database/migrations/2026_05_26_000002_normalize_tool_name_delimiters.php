<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['tool_feedback', 'tool_invocations'] as $table) {
            DB::table($table)
                ->whereNotNull('tool_name')
                ->where('tool_name', 'like', '%\_%')
                ->orderBy('id')
                ->select(['id', 'tool_name'])
                ->chunkById(500, function ($rows) use ($table): void {
                    foreach ($rows as $row) {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->update([
                                'tool_name' => str_replace('_', '-', (string) $row->tool_name),
                            ]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Data normalization is intentionally not reversed.
    }
};
