<?php

namespace App\Models;

use App\Support\Lens;
use App\Support\WorkspaceContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public static bool $suppressDefaultWorkspace = false;

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            if (static::$suppressDefaultWorkspace) {
                return;
            }

            $workspace = Workspace::create([
                'name' => 'Personal',
                'slug' => Workspace::uniqueSlug($user->name ?: $user->email ?: 'workspace'),
                'owner_user_id' => $user->id,
            ]);

            WorkspaceMembership::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => WorkspaceMembership::ROLE_OWNER,
            ]);

            $user->switchWorkspace($workspace);
        });
    }

    /**
     * Run the given callback while suppressing the auto-created personal workspace.
     */
    public static function withoutDefaultWorkspace(\Closure $callback): mixed
    {
        $previous = static::$suppressDefaultWorkspace;
        static::$suppressDefaultWorkspace = true;

        try {
            return $callback();
        } finally {
            static::$suppressDefaultWorkspace = $previous;
        }
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function activeWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'active_workspace_id');
    }

    public function switchWorkspace(Workspace|string $workspace): void
    {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;

        DB::transaction(function () use ($workspaceId): void {
            $this->forceFill(['active_workspace_id' => $workspaceId])->save();

            WorkspaceMembership::query()
                ->where('workspace_id', $workspaceId)
                ->where('user_id', $this->id)
                ->update(['last_accessed_at' => now()]);
        });

        app(WorkspaceContext::class)->forget();
    }

    /**
     * The user's active Lens, derived from Role Capabilities on the selected
     * project. Workspace owners/admins with no project Role see every section.
     */
    public function lens(): Lens
    {
        $project = $this->selectedProject();

        if ($project === null) {
            return Lens::all();
        }

        $roles = $this->roles()
            ->where('project_id', $project->id)
            ->with('capabilityAssignments')
            ->get();

        if ($roles->isEmpty()) {
            return $this->isWorkspaceMutator($project->workspace_id) ? Lens::all() : Lens::empty();
        }

        $capabilities = $roles->flatMap(fn (Role $role) => $role->capabilities());

        // A workspace owner/admin who self-assigned only capability-less roles
        // would otherwise fall into an empty Lens (and an empty Project nav).
        // Treat that exactly like the no-role case so the mutator keeps the
        // see-all fallback — assigning yourself a role shouldn't silently strip
        // your own navigation.
        if ($capabilities->isEmpty() && $this->isWorkspaceMutator($project->workspace_id)) {
            return Lens::all();
        }

        return Lens::fromCapabilities($capabilities);
    }

    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_user_id');
    }

    public function personalWorkspace(): HasOne
    {
        return $this->hasOne(Workspace::class, 'owner_user_id')->oldest('id');
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_memberships')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function workspaceMemberships(): HasMany
    {
        return $this->hasMany(WorkspaceMembership::class);
    }

    public function roles(): MorphToMany
    {
        return $this->morphToMany(Role::class, 'assignable');
    }

    private function selectedProject(): ?Project
    {
        $projectId = (string) request()->query('project', '') ?: (string) session('selected_project_id', '');

        if ($projectId !== '') {
            return Project::query()->find($projectId);
        }

        return Project::query()
            ->where('workspace_id', $this->active_workspace_id)
            ->oldest()
            ->first();
    }

    private function isWorkspaceMutator(string $workspaceId): bool
    {
        return WorkspaceMembership::query()
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $this->id)
            ->whereIn('role', [WorkspaceMembership::ROLE_OWNER, WorkspaceMembership::ROLE_ADMIN])
            ->exists();
    }
}
