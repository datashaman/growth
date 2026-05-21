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
        Schema::create('role_capabilities', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('role_id')->constrained()->cascadeOnDelete();
            $table->string('capability', 64);
            $table->timestamps();

            $table->unique(['role_id', 'capability']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_capabilities');
    }
};
