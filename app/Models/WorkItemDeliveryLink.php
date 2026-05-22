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

    /**
     * Canonical storage form of a delivery-link ref. Pull-request refs (and an
     * `evidence` ref that is itself a pull-request ref) collapse to `#<number>`
     * so the same PR — supplied as `14`, `#14`, `PR-14`, or a `.../pull/14`
     * URL — always resolves to one row. `branch` and `commit` refs, and any
     * `evidence` ref that is not a pull-request ref, are returned unchanged.
     *
     * This is the single source of truth for the rule; the upsert tool and the
     * one-time dedupe command both call it.
     */
    public static function canonicalRef(string $type, string $ref): string
    {
        if ($type !== 'pull_request' && $type !== 'evidence') {
            return $ref;
        }

        $number = self::pullRequestNumber($ref);

        return $number === null ? $ref : '#'.$number;
    }

    /**
     * Extract the pull-request number from any of its accepted forms, or null
     * when the ref is not a pull-request ref. Forms: a `.../pull/<n>` URL, a
     * leading `#<n>`, `PR-<n>`/`PR <n>`, `pull request <n>`, or a bare `<n>`.
     */
    private static function pullRequestNumber(string $ref): ?int
    {
        $ref = trim($ref);

        if (preg_match('#/pull/(\d+)#', $ref, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('/^(?:#|pr[-\s]?|pull\s+request\s*#?)?(\d+)$/i', $ref, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

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
