<?php

namespace App\Support;

/**
 * Helpers for deciding whether a table column is worth rendering.
 *
 * Several webapp tables render columns that are empty for every row in view —
 * the column consumes width (and, with table-fixed, squeezes the columns that
 * do carry data) while conveying nothing. Hide such a column entirely.
 *
 * Emptiness is judged on the raw model attribute via the accessor, not on the
 * rendered cell: placeholders like "—", "unbound", or "no runs" still mean
 * "no value". `filled()` treats null, '', and [] as empty.
 */
class TableColumn
{
    /**
     * Whether at least one row carries a value for this column — i.e. whether
     * the column should render at all.
     *
     * @param  iterable<mixed>  $rows
     * @param  callable(mixed): mixed  $value  Accessor returning the raw cell value for a row.
     */
    public static function hasValues(iterable $rows, callable $value): bool
    {
        foreach ($rows as $row) {
            if (filled($value($row))) {
                return true;
            }
        }

        return false;
    }
}
