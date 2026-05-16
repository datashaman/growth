<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separator that has historically followed a "CR-NNN" prefix in titles:
     * an em dash or a colon, with optional surrounding whitespace.
     */
    private const PREFIX_PATTERN = '/^CR-0*(\d+)\s*(?:\x{2014}|:|-)\s*/u';

    public function up(): void
    {
        Schema::table('change_requests', function (Blueprint $table) {
            $table->unsignedInteger('number')->nullable()->after('id');
        });

        $this->backfillNumbers();

        Schema::table('change_requests', function (Blueprint $table) {
            $table->unique(['project_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::table('change_requests', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'number']);
            $table->dropColumn('number');
        });
    }

    /**
     * Assign each change request a per-project sequential number, reusing the
     * "CR-NNN" value embedded in its title where it does not collide, and
     * stripping that prefix from the stored title.
     */
    private function backfillNumbers(): void
    {
        $projectIds = DB::table('change_requests')
            ->distinct()
            ->pluck('project_id');

        foreach ($projectIds as $projectId) {
            $rows = DB::table('change_requests')
                ->where('project_id', $projectId)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'title']);

            $parsed = $rows->map(function (object $row): array {
                $number = preg_match(self::PREFIX_PATTERN, $row->title, $matches)
                    ? (int) $matches[1]
                    : null;

                return [
                    'id' => $row->id,
                    'number' => $number,
                    'title' => preg_replace(self::PREFIX_PATTERN, '', $row->title),
                ];
            });

            $taken = [];
            foreach ($parsed as $entry) {
                if ($entry['number'] !== null && ! isset($taken[$entry['number']])) {
                    $taken[$entry['number']] = $entry['id'];
                }
            }

            $next = $taken === [] ? 0 : max(array_keys($taken));

            foreach ($parsed as $entry) {
                $number = $entry['number'];
                if ($number === null || ($taken[$number] ?? null) !== $entry['id']) {
                    $number = ++$next;
                    $taken[$number] = $entry['id'];
                }

                DB::table('change_requests')
                    ->where('id', $entry['id'])
                    ->update([
                        'number' => $number,
                        'title' => $entry['title'],
                    ]);
            }
        }
    }
};
