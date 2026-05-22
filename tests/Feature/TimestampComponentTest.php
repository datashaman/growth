<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Blade;

test('timestamp component exposes utc source data for browser-local formatting', function () {
    $html = Blade::render('<x-timestamp :value="$value" format="datetime" />', [
        'value' => Carbon::parse('2026-05-22 10:15:00', 'UTC'),
    ]);

    expect($html)
        ->toContain('data-local-time')
        ->toContain('data-format="datetime"')
        ->toContain('datetime="2026-05-22T10:15:00.000000Z"')
        ->toContain('GrowthFormatLocalTimes')
        ->toContain('2026-05-22 10:15');
});
