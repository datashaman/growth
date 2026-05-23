<?php

namespace App\Models;

use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeRequestDeliveryLink extends Model
{
    use HasUlids;
    use ScopedByOwner;

    public const OWNER_SCOPE_RELATION = 'changeRequest.project';

    public const TYPES = ['commit', 'pull_request', 'branch'];

    protected $fillable = [
        'change_request_id', 'type', 'ref', 'url', 'description',
    ];

    public static function canonicalRef(string $type, string $ref): string
    {
        if ($type !== 'pull_request') {
            return $ref;
        }

        $number = self::pullRequestNumber($ref);

        return $number === null ? $ref : '#'.$number;
    }

    private static function pullRequestNumber(string $ref): ?int
    {
        $ref = trim($ref);

        if (preg_match('#/pull/(\d+)#', $ref, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('/^(?:#|pr[-\s]?|pull\s+request\s*#?)?(\d+)$/i', $ref, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class);
    }
}
