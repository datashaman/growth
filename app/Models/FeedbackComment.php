<?php

namespace App\Models;

use Database\Factories\FeedbackCommentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackComment extends Model
{
    /** @use HasFactory<FeedbackCommentFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'tool_feedback_id', 'user_id', 'acting_surface',
        'acting_role_id', 'acting_role_name', 'body',
    ];

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(ToolFeedback::class, 'tool_feedback_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actingRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'acting_role_id');
    }
}
