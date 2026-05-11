<?php

namespace App\Models;

use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignElement extends Model
{
    use HasUlids;
    use ScopedByOwner;

    public const OWNER_SCOPE_RELATION = 'view';

    protected $fillable = ['design_view_id', 'kind', 'name', 'type', 'purpose', 'properties'];

    protected $casts = [
        'properties' => 'array',
    ];

    public function view(): BelongsTo
    {
        return $this->belongsTo(DesignView::class, 'design_view_id');
    }
}
