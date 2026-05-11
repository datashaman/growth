<?php

namespace App\Models;

use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ChangeImpact extends Model
{
    use HasUlids;
    use ScopedByOwner;

    public const OWNER_SCOPE_RELATION = 'changeRequest.project';

    public const KINDS = ['creates', 'modifies', 'replaces', 'deprecates', 'removes', 'needs_analysis'];

    protected $fillable = [
        'change_request_id', 'impactable_type', 'impactable_id',
        'impact_kind', 'description',
    ];

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class);
    }

    public function impactable(): MorphTo
    {
        return $this->morphTo();
    }
}
