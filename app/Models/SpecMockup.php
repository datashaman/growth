<?php

namespace App\Models;

use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SpecMockup extends Model
{
    use HasUlids;
    use ScopedByOwner;

    public const OWNER_SCOPE_RELATION = 'owner';

    /**
     * Implicit mockup name used when a caller upserts or fetches a mockup
     * without passing one — the "one mockup per owner" default. Callers can
     * still hold additional named alternatives by passing `name` explicitly.
     */
    public const DEFAULT_NAME = 'default';

    protected $fillable = [
        'owner_type', 'owner_id', 'name',
    ];

    /**
     * The spec entity this mockup is a visual companion to — a work item
     * or a requirement.
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(SpecMockupRevision::class)->orderBy('number');
    }

    /**
     * The mockup's current state — its highest-numbered revision.
     */
    public function currentRevision(): HasOne
    {
        return $this->hasOne(SpecMockupRevision::class)->latestOfMany('number');
    }

    /**
     * Append a new round of HTML as the next revision and return it.
     */
    public function appendRevision(string $html): SpecMockupRevision
    {
        return $this->revisions()->create([
            'number' => ($this->revisions()->max('number') ?? 0) + 1,
            'html' => $html,
        ]);
    }
}
