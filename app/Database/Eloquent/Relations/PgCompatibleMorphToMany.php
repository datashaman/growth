<?php

namespace App\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;

/**
 * Polymorphic many-to-many that survives heterogeneous related-key types on
 * Postgres. The `assignable_id` column is varchar to hold both bigint user
 * ids and ULID agent ids, but Postgres refuses `bigint = varchar` joins. We
 * cast the related key to text on pgsql only when its type is `int`.
 */
class PgCompatibleMorphToMany extends MorphToMany
{
    protected function performJoin($query = null)
    {
        $query = $query ?: $this->query;

        $driver = $query->getConnection()->getDriverName();
        $needsCast = $driver === 'pgsql' && $this->getRelated()->getKeyType() === 'int';

        if (! $needsCast) {
            return parent::performJoin($query);
        }

        $query->join(
            $this->table,
            DB::raw('CAST('.$this->getQualifiedRelatedKeyName().' AS TEXT)'),
            '=',
            $this->getQualifiedRelatedPivotKeyName()
        );

        return $this;
    }
}
