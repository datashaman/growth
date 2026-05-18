<?php

namespace App\Models;

use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkItemDeliveryLink extends Model
{
    use HasUlids;
    use ScopedByOwner;

    public const OWNER_SCOPE_RELATION = 'workItem.project';

    public const TYPES = ['commit', 'pull_request', 'branch', 'evidence'];

    protected $fillable = [
        'work_item_id', 'type', 'ref', 'url', 'description',
    ];

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    public function checkRuns(): HasMany
    {
        return $this->hasMany(CheckRunEvidence::class);
    }

    public function deployments(): BelongsToMany
    {
        return $this->belongsToMany(Deployment::class, 'deployment_delivery_link')
            ->withTimestamps();
    }
}
