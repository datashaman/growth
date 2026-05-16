<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_transitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulidMorphs('transitionable');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->foreignId('transitioned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('transitioned_at');
            $table->timestamps();

            $table->index(['transitionable_type', 'transitionable_id', 'transitioned_at'], 'status_transitions_subject_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_transitions');
    }
};
