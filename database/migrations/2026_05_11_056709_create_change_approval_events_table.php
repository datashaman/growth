<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_approval_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('change_request_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->string('from_decision')->nullable();
            $table->string('to_decision')->nullable();
            $table->text('rationale')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['change_request_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_approval_events');
    }
};
