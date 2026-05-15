<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('source_id')->constrained()->cascadeOnDelete();
            $table->string('citable_type', 64);
            $table->char('citable_id', 26);
            $table->text('quote')->nullable();
            $table->string('locator', 191)->nullable();
            $table->timestamps();

            $table->index(['citable_type', 'citable_id']);
            $table->unique(['source_id', 'citable_type', 'citable_id', 'locator'], 'citations_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citations');
    }
};
