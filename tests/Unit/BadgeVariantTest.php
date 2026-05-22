<?php

/*
 * #372: priority colours read consistently across surfaces, `medium` is not a
 * caution colour, and the gate/finding severity axes share one colour scale.
 */

use App\Support\BadgeVariant;

test('the priority scale is monotonic and shared across surfaces', function () {
    // One method backs every priority surface, so a word maps to one colour.
    expect(BadgeVariant::priority('critical'))->toBe('red');
    expect(BadgeVariant::priority('high'))->toBe('orange');
    expect(BadgeVariant::priority('medium'))->toBe('sky');
    expect(BadgeVariant::priority('low'))->toBe('zinc');
});

test('medium priority does not use the amber warning colour', function () {
    expect(BadgeVariant::priority('medium'))->not->toBe('amber');
});

test('red is reserved for critical so it outranks high', function () {
    expect(BadgeVariant::priority('critical'))->toBe('red');
    expect(BadgeVariant::priority('high'))->not->toBe('red');
});

test('the gate and finding severity axes share one colour scale', function () {
    expect(BadgeVariant::gate('fail'))->toBe(BadgeVariant::finding('error'));
    expect(BadgeVariant::gate('warn'))->toBe(BadgeVariant::finding('warning'));
    expect(BadgeVariant::gate('fail'))->toBe('red');
    expect(BadgeVariant::gate('warn'))->toBe('amber');
    expect(BadgeVariant::gate('pass'))->toBe('green');
});

test('unknown priority and severity values fall back to neutral', function () {
    expect(BadgeVariant::priority(null))->toBe('zinc');
    expect(BadgeVariant::priority('bogus'))->toBe('zinc');
    expect(BadgeVariant::finding('info'))->toBe('zinc');
});
