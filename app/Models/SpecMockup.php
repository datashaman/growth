<?php

namespace App\Models;

use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SpecMockup extends Model
{
    use HasUlids;
    use ScopedByOwner;

    public const OWNER_SCOPE_RELATION = 'workItem.project';

    protected $fillable = [
        'work_item_id', 'name',
    ];

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
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
