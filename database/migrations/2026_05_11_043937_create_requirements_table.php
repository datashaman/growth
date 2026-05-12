<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('requirements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('parent_id')->nullable();
            $table->enum('doc', ['strs', 'syrs', 'srs']);
            $table->enum('type', [
                'functional', 'performance', 'usability', 'interface',
                'design_constraint', 'process', 'non_functional',
            ]);
            $table->text('text');
            $table->text('rationale')->nullable();
            $table->string('source')->nullable();
            $table->enum('priority', ['high', 'medium', 'low'])->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'doc']);
        });

        Schema::table('requirements', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('requirements')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requirements');
    }
};
