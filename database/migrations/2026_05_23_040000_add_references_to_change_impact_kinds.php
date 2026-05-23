<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admit non-mutating change impacts for artifacts that are context rather
     * than targets being changed.
     */
    public function up(): void
    {
        $this->resetImpactKindConstraint(['creates', 'modifies', 'replaces', 'deprecates', 'removes', 'needs_analysis', 'references']);
    }

    public function down(): void
    {
        DB::table('change_impacts')->where('impact_kind', 'references')->delete();

        $this->resetImpactKindConstraint(['creates', 'modifies', 'replaces', 'deprecates', 'removes', 'needs_analysis']);
    }

    /**
     * @param  list<string>  $allowed
     */
    private function resetImpactKindConstraint(array $allowed): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE change_impacts DROP CONSTRAINT IF EXISTS change_impacts_impact_kind_check');
            $values = implode(', ', array_map(fn (string $v): string => "'{$v}'", $allowed));
            DB::statement("ALTER TABLE change_impacts ADD CONSTRAINT change_impacts_impact_kind_check CHECK (impact_kind IN ({$values}))");

            return;
        }

        Schema::table('change_impacts', function (Blueprint $table) use ($allowed): void {
            $table->enum('impact_kind', $allowed)->change();
        });
    }
};
