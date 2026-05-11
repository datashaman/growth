<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requirement_work_item', function (Blueprint $table) {
            $table->foreignUlid('work_item_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('requirement_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['work_item_id', 'requirement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requirement_work_item');
    }
};
