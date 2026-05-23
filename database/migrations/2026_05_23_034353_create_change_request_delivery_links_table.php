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
        Schema::create('change_request_delivery_links', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('change_request_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['commit', 'pull_request', 'branch']);
            $table->string('ref');
            $table->string('url')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['change_request_id', 'type', 'ref']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('change_request_delivery_links');
    }
};
