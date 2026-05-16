<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsProjectChanges;
use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Release extends Model
{
    use BroadcastsProjectChanges;
    use HasUlids;
    use ScopedByOwner;

    public const STATUSES = ['planned', 'candidate', 'released', 'cancelled'];

    protected $fillable = [
        'project_id', 'version', 'name', 'status', 'released_at', 'notes',
    ];

    protected $casts = [
        'released_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workItems(): BelongsToMany
    {
        return $this->belongsToMany(WorkItem::class, 'release_work_item')
            ->withTimestamps();
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }
}
