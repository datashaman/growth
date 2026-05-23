<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('theme_id')->constrained()->cascadeOnDelete();
            $table->string('scope_type');
            $table->string('scope_key');
            $table->string('label')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'scope_type', 'scope_key']);
            $table->index(['project_id', 'theme_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_assignments');
    }
};
