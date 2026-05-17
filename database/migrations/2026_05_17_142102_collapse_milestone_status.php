<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });

        DB::table('milestones')->where('status', 'hit')->update(['status' => 'achieved']);
        DB::table('milestones')->whereIn('status', ['missed', 'deferred'])->update(['status' => 'pending']);

        Schema::table('milestones', function (Blueprint $table) {
            $table->enum('status', ['pending', 'achieved'])->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });

        DB::table('milestones')->where('status', 'achieved')->update(['status' => 'hit']);

        Schema::table('milestones', function (Blueprint $table) {
            $table->enum('status', ['pending', 'hit', 'missed', 'deferred'])->default('pending')->change();
        });
    }
};
