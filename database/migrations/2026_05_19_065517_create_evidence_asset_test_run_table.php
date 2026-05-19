<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivot: which screenshots a verification run carries as visual
        // evidence. A run cites many assets; an asset can back many runs (#245).
        Schema::create('evidence_asset_test_run', function (Blueprint $table) {
            $table->foreignUlid('test_run_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('evidence_asset_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['test_run_id', 'evidence_asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_asset_test_run');
    }
};
