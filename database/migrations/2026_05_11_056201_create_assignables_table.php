<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignables', function (Blueprint $table) {
            $table->foreignUlid('role_id')->constrained()->cascadeOnDelete();
            $table->string('assignable_type');
            $table->string('assignable_id');
            $table->timestamps();

            $table->unique(['role_id', 'assignable_type', 'assignable_id'], 'assignables_unique');
            $table->index(['assignable_type', 'assignable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignables');
    }
};
