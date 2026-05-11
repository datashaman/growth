<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->enum('kind', [
                'brief', 'rfp', 'interview', 'transcript', 'contract',
                'source', 'ticket', 'email', 'doc', 'prototype', 'other',
            ]);
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('uri')->nullable();
            $table->string('external_ref')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
