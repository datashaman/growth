<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_assets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('work_item_delivery_link_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('caption');
            $table->string('content_type')->default('image/png');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_assets');
    }
};
