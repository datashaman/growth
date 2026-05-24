<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('spec_mockups', 'mockups');
        Schema::rename('spec_mockup_revisions', 'mockup_revisions');
        Schema::table('mockup_revisions', function (Blueprint $table): void {
            $table->renameColumn('spec_mockup_id', 'mockup_id');
        });
    }

    public function down(): void
    {
        Schema::table('mockup_revisions', function (Blueprint $table): void {
            $table->renameColumn('mockup_id', 'spec_mockup_id');
        });
        Schema::rename('mockup_revisions', 'spec_mockup_revisions');
        Schema::rename('mockups', 'spec_mockups');
    }
};
