<?php

use App\Models\Requirement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requirements', function (Blueprint $table): void {
            $table->string('slug', 120)->nullable()->after('id');
        });

        // Backfill: deterministic kebab-case from `text`, truncated to 100 chars,
        // disambiguated within (project_id) with -2, -3, … suffixes.
        Requirement::query()
            ->select('id', 'project_id', 'text')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                $usedByProject = [];
                foreach ($rows as $row) {
                    $base = Str::limit(Str::slug((string) $row->text), 100, '');
                    if ($base === '') {
                        $base = 'requirement';
                    }
                    $used = $usedByProject[$row->project_id] ?? [];
                    $slug = $base;
                    $n = 2;
                    while (in_array($slug, $used, true)
                        || DB::table('requirements')
                            ->where('project_id', $row->project_id)
                            ->where('slug', $slug)
                            ->where('id', '!=', $row->id)
                            ->exists()
                    ) {
                        $slug = $base.'-'.$n;
                        $n++;
                    }
                    DB::table('requirements')->where('id', $row->id)->update(['slug' => $slug]);
                    $usedByProject[$row->project_id][] = $slug;
                }
            });

        Schema::table('requirements', function (Blueprint $table): void {
            $table->string('slug', 120)->nullable(false)->change();
            $table->unique(['project_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('requirements', function (Blueprint $table): void {
            $table->dropUnique(['project_id', 'slug']);
            $table->dropColumn('slug');
        });
    }
};
