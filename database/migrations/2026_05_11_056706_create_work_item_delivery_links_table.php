<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_item_delivery_links', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('work_item_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['commit', 'pull_request', 'branch']);
            $table->string('ref');
            $table->string('url')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['work_item_id', 'type', 'ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_item_delivery_links');
    }
};
