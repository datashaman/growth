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

    protected static function booted(): void
    {
        // Evidence assets carry S3 objects the database cannot cascade. An FK
        // cascade would drop the rows without firing model events, orphaning
        // the objects — so delete the assets through the model layer here.
        static::deleting(function (WorkItemDeliveryLink $deliveryLink): void {
            $deliveryLink->evidenceAssets->each->delete();
        });
    }

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    public function checkRuns(): HasMany
    {
        return $this->hasMany(CheckRunEvidence::class);
    }

    public function evidenceAssets(): HasMany
    {
        return $this->hasMany(EvidenceAsset::class);
    }

    public function deployments(): BelongsToMany
    {
        return $this->belongsToMany(Deployment::class, 'deployment_delivery_link')
            ->withTimestamps();
    }
}
