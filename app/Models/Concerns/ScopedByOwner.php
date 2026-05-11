<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Scopes the model to rows whose project is owned by the authenticated
 * user. Walks back to Project via the relation named by static::ownerScopeRelation()
 * (default: "project"); the relation already applies its own scope, so a single
 * whereHas chain — even multiple hops — filters correctly without enumerating
 * every link.
 *
 * No-op when no user is authenticated, matching the local-stdio assumption.
 */
trait ScopedByOwner
{
    protected static function bootScopedByOwner(): void
    {
        static::addGlobalScope('owner', function (Builder $query): void {
            if (! auth()->check()) {
                return;
            }

            $relation = defined(static::class.'::OWNER_SCOPE_RELATION')
                ? static::OWNER_SCOPE_RELATION
                : 'project';

            $query->whereHas($relation);
        });
    }
}
