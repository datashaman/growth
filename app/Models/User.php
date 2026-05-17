<?php

namespace App\Models;

use App\Support\ViewLens;
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
            'view_lens' => ViewLens::class,
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
     * The user's active view lens; defaults to All when none is set.
     */
    public function lens(): ViewLens
    {
        return $this->view_lens ?? ViewLens::All;
    }

    public function switchLens(ViewLens $lens): void
    {
        $this->forceFill(['view_lens' => $lens])->save();
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
}
