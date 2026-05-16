<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsProjectChanges;
use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Concern extends Model
{
    use BroadcastsProjectChanges;
    use HasUlids;
    use ScopedByOwner;

    protected $fillable = ['project_id', 'raised_by_stakeholder_id', 'text', 'viewpoint_hints'];

    protected $casts = [
        'viewpoint_hints' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(Stakeholder::class, 'raised_by_stakeholder_id');
    }

    public function designViews(): BelongsToMany
    {
        return $this->belongsToMany(DesignView::class, 'concern_design_view');
    }

    public function citations(): MorphMany
    {
        return $this->morphMany(Citation::class, 'citable');
    }
}
