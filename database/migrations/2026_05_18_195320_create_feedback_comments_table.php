<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A feedback comment turns a feedback entry into a two-way thread: the
     * filer and triagers can follow up after the original submission. Each
     * comment is attributed to its author and the role they were acting in.
     */
    public function up(): void
    {
        Schema::create('feedback_comments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tool_feedback_id')->constrained('tool_feedback')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('acting_role')->nullable();
            $table->text('body');
            $table->timestamps();

            $table->index(['tool_feedback_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_comments');
    }
};
