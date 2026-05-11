<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_item_dependencies', function (Blueprint $table) {
            $table->foreignUlid('work_item_id')->constrained('work_items')->cascadeOnDelete();
            $table->foreignUlid('depends_on_id')->constrained('work_items')->cascadeOnDelete();
            $table->enum('kind', ['finish_to_start', 'start_to_start', 'finish_to_finish', 'start_to_finish'])
                ->default('finish_to_start');
            $table->timestamps();

            $table->primary(['work_item_id', 'depends_on_id'], 'work_item_dependencies_pk');
            $table->index('depends_on_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_item_dependencies');
    }
};
