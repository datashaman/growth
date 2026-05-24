<?php

namespace App\Support;

use App\Models\Anomaly;
use App\Models\ChangeRequest;
use App\Models\Mockup;
use App\Models\Requirement;
use App\Models\Review;
use App\Models\Risk;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Resolves a polymorphic artifact (a morph-typed `{type, id}` reference) to a
 * human-readable label and, where a webapp detail page exists, a link to it.
 *
 * Centralises the type → name/route mapping so every surface that renders an
 * artifact reference (change-request impacts, …) stays consistent rather than
 * leaking raw `type:ULID` machine identifiers.
 */
class ArtifactLink
{
    /**
     * Detail-page route URL for the artifact, or null when its type has no
     * browsable detail page in the webapp.
     */
    public static function route(Model $artifact): ?string
    {
        return match (true) {
            $artifact instanceof WorkItem => route('work-items.show', $artifact),
            $artifact instanceof Requirement => route('requirements.show', $artifact),
            $artifact instanceof Review => route('reviews.show', $artifact),
            $artifact instanceof Anomaly => route('anomalies.show', $artifact),
            $artifact instanceof Risk => route('risks.show', $artifact),
            $artifact instanceof ChangeRequest => route('change-requests.show', $artifact),
            $artifact instanceof Mockup => route('mockups.show', $artifact),
            default => null,
        };
    }

    /**
     * Human-readable name for the artifact, falling back through the common
     * label attributes and finally to a type + short-id descriptor.
     */
    public static function label(Model $artifact): string
    {
        if ($artifact instanceof WorkItem) {
            return trim($artifact->reference().' — '.$artifact->name, ' —');
        }

        foreach (['name', 'title', 'summary'] as $attribute) {
            $value = $artifact->getAttribute($attribute);

            if (filled($value)) {
                return $value;
            }
        }

        $text = $artifact->getAttribute('text');

        if (filled($text)) {
            return Str::limit($text, 60);
        }

        return self::typeLabel($artifact->getMorphClass()).' '.Str::limit((string) $artifact->getKey(), 8, '…');
    }

    /**
     * Humanise a stored morph type (e.g. `work_item` → `work item`) for the
     * case where the artifact itself can no longer be resolved.
     */
    public static function typeLabel(string $type): string
    {
        return str_replace('_', ' ', class_basename($type));
    }
}
