<?php

namespace App\Models;

use Database\Factories\DecisionRequestOptionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One choice a {@see DecisionRequest} can be answered with.
 */
class DecisionRequestOption extends Model
{
    /** @use HasFactory<DecisionRequestOptionFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'decision_request_id', 'label', 'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function decisionRequest(): BelongsTo
    {
        return $this->belongsTo(DecisionRequest::class);
    }
}
