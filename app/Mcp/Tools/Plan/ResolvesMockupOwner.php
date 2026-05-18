<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Requirement;
use App\Models\WorkItem;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Validates the polymorphic owner of a spec mockup — a work item or a
 * requirement — against the active workspace.
 */
trait ResolvesMockupOwner
{
    /** @var array<string, class-string<Model>> */
    private const OWNER_MODELS = [
        'work_item' => WorkItem::class,
        'requirement' => Requirement::class,
    ];

    /**
     * A closure rule asserting the owner id names an existing record of the
     * given type. The model's ownership scope applies, so an owner outside
     * the active workspace is rejected as invalid.
     */
    private function ownerExistsRule(mixed $ownerType): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ownerType): void {
            $model = self::OWNER_MODELS[$ownerType] ?? null;

            if ($model === null || ! is_string($value) || ! $model::where('id', $value)->exists()) {
                $fail('The selected :attribute is invalid.');
            }
        };
    }
}
