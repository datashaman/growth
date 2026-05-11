<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milestone_work_item', function (Blueprint $table) {
            $table->foreignUlid('milestone_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('work_item_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['milestone_id', 'work_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestone_work_item');
    }
};
