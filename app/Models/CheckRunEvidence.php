<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsViaProjectRelation;
use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckRunEvidence extends Model
{
    use BroadcastsViaProjectRelation;
    use HasUlids;
    use ScopedByOwner;

    public function projectIdForBroadcast(): ?string
    {
        return $this->deliveryLink?->workItem?->project_id;
    }

    public const OWNER_SCOPE_RELATION = 'deliveryLink.workItem.project';

    public const STATUSES = ['queued', 'in_progress', 'completed'];

    public const CONCLUSIONS = ['success', 'failure', 'cancelled', 'skipped', 'neutral', 'timed_out', 'action_required'];

    protected $table = 'check_run_evidences';

    protected $fillable = [
        'work_item_delivery_link_id', 'provider', 'name', 'run_ref',
        'status', 'conclusion', 'url', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function deliveryLink(): BelongsTo
    {
        return $this->belongsTo(WorkItemDeliveryLink::class, 'work_item_delivery_link_id');
    }
}
