<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsProjectChanges;
use App\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThemeAssignment extends Model
{
    use BroadcastsProjectChanges;
    use HasUlids;

    protected $fillable = [
        'project_id',
        'theme_id',
        'scope_type',
        'scope_key',
        'label',
        'notes',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('workspace', function (Builder $query): void {
            $workspaceId = app(WorkspaceContext::class)->id();

            if ($workspaceId !== null) {
                $query->whereHas('project', fn (Builder $project): Builder => $project->where('workspace_id', $workspaceId));
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function scopeLabel(): string
    {
        $label = filled($this->label) ? " ({$this->label})" : '';

        return "{$this->scope_type}:{$this->scope_key}{$label}";
    }
}
