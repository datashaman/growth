<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_participants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('role_id')->constrained()->cascadeOnDelete();
            $table->enum('responsibility', ['moderator', 'author', 'reviewer', 'recorder', 'auditor', 'observer', 'approver']);
            $table->enum('attendance_status', ['invited', 'attended', 'absent', 'excused'])->default('invited');
            $table->timestamp('signed_off_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['review_id', 'role_id', 'responsibility'], 'review_participants_unique');
            $table->index(['review_id', 'responsibility']);
            $table->index(['role_id', 'responsibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_participants');
    }
};
