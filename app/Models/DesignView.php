<?php

namespace App\Models;

use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DesignView extends Model
{
    use HasUlids;
    use ScopedByOwner;

    public const BUILTIN_VIEWPOINTS = [
        'context', 'composition', 'logical', 'dependency', 'information',
        'patterns', 'interface', 'structure', 'interaction', 'state_dynamics',
        'algorithm', 'resource',
    ];

    protected $fillable = ['project_id', 'viewpoint', 'name', 'description'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function elements(): HasMany
    {
        return $this->hasMany(DesignElement::class);
    }

    public function concerns(): BelongsToMany
    {
        return $this->belongsToMany(Concern::class, 'concern_design_view');
    }

    public function citations(): MorphMany
    {
        return $this->morphMany(Citation::class, 'citable');
    }
}
