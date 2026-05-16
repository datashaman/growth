<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artifact_relations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->string('source_artifact_type', 64);
            $table->char('source_artifact_id', 26);
            $table->enum('relation', ['supersedes', 'replaces', 'duplicates', 'relates_to']);
            $table->string('target_artifact_type', 64);
            $table->char('target_artifact_id', 26);
            $table->text('rationale')->nullable();
            $table->timestamps();

            $table->unique([
                'project_id',
                'source_artifact_type',
                'source_artifact_id',
                'relation',
                'target_artifact_type',
                'target_artifact_id',
            ], 'artifact_relations_unique');
            $table->index(['source_artifact_type', 'source_artifact_id']);
            $table->index(['target_artifact_type', 'target_artifact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artifact_relations');
    }
};
