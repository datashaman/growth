<?php

use Illuminate\Support\Facades\Blade;

/*
 * Flux marks the whole <table> `whitespace-nowrap`; combined with `table-fixed`
 * that lets a long cell overflow its column and clip trailing columns off the
 * right edge (issue #361). The shared <x-data-table> section overrides
 * `white-space`/`overflow-wrap` on data cells so prose wraps inside its column.
 * Guard the override against accidental removal.
 */
test('the shared data table forces data cells to wrap', function () {
    $html = Blade::render('<x-data-table><span>body</span></x-data-table>');

    expect($html)
        ->toContain('[&_td]:whitespace-normal')
        ->toContain('[&_td]:break-words');
});
