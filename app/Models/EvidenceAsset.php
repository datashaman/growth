<?php

namespace App\Models;

use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class EvidenceAsset extends Model
{
    use HasUlids;
    use ScopedByOwner;

    public const OWNER_SCOPE_RELATION = 'deliveryLink.workItem.project';

    /**
     * The filesystem disk that backs evidence assets. Growth deploys to Vapor
     * (ephemeral Lambda filesystem) as well as Forge, so the bytes must live
     * on durable object storage rather than the local disk.
     */
    public const DISK = 's3';

    protected $fillable = [
        'work_item_delivery_link_id', 'path', 'caption', 'content_type',
    ];

    protected static function booted(): void
    {
        // The S3 object is not tracked by the database, so it cannot be
        // removed by an FK cascade. Drop it here whenever the row is deleted
        // through the model layer — including the explicit descendant
        // deletion the ownership-chain parents perform on their own delete.
        static::deleting(function (EvidenceAsset $asset): void {
            Storage::disk(self::DISK)->delete($asset->path);
        });
    }

    public function deliveryLink(): BelongsTo
    {
        return $this->belongsTo(WorkItemDeliveryLink::class, 'work_item_delivery_link_id');
    }

    /**
     * The stable, backend-agnostic public URL for this asset. Embedded in
     * GitHub gallery comments, so it must outlive any particular storage
     * backend — it resolves through Growth's own image-serving route, never
     * a raw S3 object URL.
     */
    public function publicUrl(): string
    {
        return route('evidence-assets.show', $this);
    }
}
