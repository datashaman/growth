<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_request_options', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('decision_request_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['decision_request_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_request_options');
    }
};
