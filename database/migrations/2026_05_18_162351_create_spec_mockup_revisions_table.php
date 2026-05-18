<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * A spec mockup's HTML moves into an ordered series of revisions: each
     * `upsert-mockup` round appends one, so iterative refinement stays
     * recoverable. The mockup's current state is its highest-numbered
     * revision. Every existing mockup is seeded with revision 1 from the
     * `html` column it carried, which is then dropped.
     */
    public function up(): void
    {
        Schema::create('spec_mockup_revisions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('spec_mockup_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->longText('html');
            $table->timestamps();

            $table->unique(['spec_mockup_id', 'number']);
        });

        foreach (DB::table('spec_mockups')->get(['id', 'html', 'created_at', 'updated_at']) as $mockup) {
            DB::table('spec_mockup_revisions')->insert([
                'id' => (string) Str::ulid(),
                'spec_mockup_id' => $mockup->id,
                'number' => 1,
                'html' => $mockup->html,
                'created_at' => $mockup->created_at,
                'updated_at' => $mockup->updated_at,
            ]);
        }

        Schema::table('spec_mockups', function (Blueprint $table) {
            $table->dropColumn('html');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spec_mockups', function (Blueprint $table) {
            $table->longText('html')->default('');
        });

        // Restore each mockup's current state from its latest revision.
        foreach (DB::table('spec_mockups')->pluck('id') as $mockupId) {
            $latest = DB::table('spec_mockup_revisions')
                ->where('spec_mockup_id', $mockupId)
                ->orderByDesc('number')
                ->first(['html']);

            if ($latest !== null) {
                DB::table('spec_mockups')->where('id', $mockupId)->update(['html' => $latest->html]);
            }
        }

        Schema::dropIfExists('spec_mockup_revisions');
    }
};
