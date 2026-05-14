<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('workspace_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignUlid('active_workspace_id')->nullable()->after('id')->constrained('workspaces')->nullOnDelete();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignUlid('workspace_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->after('workspace_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->foreignUlid('workspace_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        $now = now();

        DB::table('users')->orderBy('id')->each(function (object $user) use ($now): void {
            $workspaceId = (string) Str::ulid();
            $slug = $this->uniqueSlug($user->name ?? $user->email ?? "user-{$user->id}");

            DB::table('workspaces')->insert([
                'id' => $workspaceId,
                'name' => $user->name ?: 'Personal',
                'slug' => $slug,
                'owner_user_id' => $user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('workspace_memberships')->insert([
                'workspace_id' => $workspaceId,
                'user_id' => $user->id,
                'role' => 'owner',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('users')->where('id', $user->id)->update([
                'active_workspace_id' => $workspaceId,
            ]);

            DB::table('projects')->where('user_id', $user->id)->update([
                'workspace_id' => $workspaceId,
                'created_by_user_id' => $user->id,
            ]);

            DB::table('oauth_access_tokens')->where('user_id', $user->id)->update([
                'workspace_id' => $workspaceId,
            ]);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('projects_user_id_index');
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignUlid('workspace_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        DB::table('projects')
            ->join('workspaces', 'projects.workspace_id', '=', 'workspaces.id')
            ->update(['projects.user_id' => DB::raw('workspaces.owner_user_id')]);

        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workspace_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workspace_id');
            $table->dropConstrainedForeignId('created_by_user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_workspace_id');
        });

        Schema::dropIfExists('workspace_memberships');
        Schema::dropIfExists('workspaces');
    }

    private function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base) ?: 'workspace';
        $candidate = $slug;
        $suffix = 2;

        while (DB::table('workspaces')->where('slug', $candidate)->exists()) {
            $candidate = "{$slug}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
};
