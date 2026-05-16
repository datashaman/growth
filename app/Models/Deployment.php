<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsProjectChanges;
use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Deployment extends Model
{
    use BroadcastsProjectChanges;
    use HasUlids;
    use ScopedByOwner;

    public const STATUSES = ['planned', 'in_progress', 'succeeded', 'failed', 'rolled_back', 'cancelled'];

    protected $fillable = [
        'project_id', 'release_id', 'environment', 'status', 'deployed_at', 'url', 'notes',
    ];

    protected $casts = [
        'deployed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public function deliveryLinks(): BelongsToMany
    {
        return $this->belongsToMany(WorkItemDeliveryLink::class, 'deployment_delivery_link')
            ->withTimestamps();
    }
}
