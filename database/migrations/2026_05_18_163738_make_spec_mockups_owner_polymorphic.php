<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A spec mockup's owner becomes polymorphic — a work item or a
     * requirement — so a visual companion can hang off either spec entity.
     * Every existing mockup is migrated to an `owner_type` of `work_item`
     * carrying its old `work_item_id`, then the fixed column is dropped.
     * The name stays unique within an owner, so the unique key follows.
     */
    public function up(): void
    {
        Schema::table('spec_mockups', function (Blueprint $table) {
            $table->string('owner_type')->nullable()->after('id');
            $table->ulid('owner_id')->nullable()->after('owner_type');
        });

        DB::table('spec_mockups')->update([
            'owner_type' => 'work_item',
            'owner_id' => DB::raw('work_item_id'),
        ]);

        Schema::table('spec_mockups', function (Blueprint $table) {
            $table->dropForeign(['work_item_id']);
            $table->dropUnique(['work_item_id', 'name']);
            $table->dropColumn('work_item_id');

            $table->string('owner_type')->nullable(false)->change();
            $table->ulid('owner_id')->nullable(false)->change();
            $table->index(['owner_type', 'owner_id']);
            $table->unique(['owner_type', 'owner_id', 'name']);
        });
    }

    /**
     * Reverse the migration. The old schema only knows work-item owners, so
     * requirement-owned mockups — which exist only because of this migration —
     * cannot survive the rollback and are dropped before `work_item_id` is
     * restored to a non-nullable, foreign-keyed column.
     */
    public function down(): void
    {
        DB::table('spec_mockups')->where('owner_type', '!=', 'work_item')->delete();

        Schema::table('spec_mockups', function (Blueprint $table) {
            $table->ulid('work_item_id')->nullable()->after('id');
        });

        DB::table('spec_mockups')
            ->where('owner_type', 'work_item')
            ->update(['work_item_id' => DB::raw('owner_id')]);

        Schema::table('spec_mockups', function (Blueprint $table) {
            $table->dropUnique(['owner_type', 'owner_id', 'name']);
            $table->dropIndex(['owner_type', 'owner_id']);
            $table->dropColumn(['owner_type', 'owner_id']);

            $table->ulid('work_item_id')->nullable(false)->change();
            $table->foreign('work_item_id')->references('id')->on('work_items')->cascadeOnDelete();
            $table->unique(['work_item_id', 'name']);
        });
    }
};
