<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raci_assignments', function (Blueprint $table) {
            $table->foreignUlid('work_item_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('role_id')->constrained()->cascadeOnDelete();
            $table->enum('raci', ['r', 'a', 'c', 'i']);
            $table->timestamps();

            $table->primary(['work_item_id', 'role_id', 'raci'], 'raci_assignments_pk');
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raci_assignments');
    }
};
