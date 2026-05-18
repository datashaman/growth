<?php

namespace App\Models;

use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One round of a {@see SpecMockup}'s HTML. Revisions are append-only and
 * ordered by `number` within their mockup; the mockup's current state is its
 * highest-numbered revision.
 */
class SpecMockupRevision extends Model
{
    use HasUlids;
    use ScopedByOwner;

    public const OWNER_SCOPE_RELATION = 'mockup.workItem.project';

    protected $fillable = [
        'spec_mockup_id', 'number', 'html',
    ];

    protected $casts = [
        'number' => 'integer',
    ];

    public function mockup(): BelongsTo
    {
        return $this->belongsTo(SpecMockup::class, 'spec_mockup_id');
    }
}
