<?php

namespace App\Models;

use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A standing interest a user has in an artifact: while a subscription exists,
 * the user is notified whenever the artifact's status transitions. The tracer
 * slice (#278) only subscribes change requests; the polymorphic shape lets
 * later work cover other artifact types without a schema change.
 *
 * The `subscribable` morph is keyed by ULID (see the migration's
 * `ulidMorphs`). Generalising to an artifact type with a bigint key would
 * need a widened column, not just a new morph-map entry.
 */
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'user_id', 'subscribable_type', 'subscribable_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }
}
