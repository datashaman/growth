<?php

use App\Support\TableColumn;

test('hasValues is false when every row is empty', function () {
    $rows = [
        (object) ['value' => null],
        (object) ['value' => ''],
        (object) ['value' => []],
    ];

    expect(TableColumn::hasValues($rows, fn ($row) => $row->value))->toBeFalse();
});

test('hasValues is true when at least one row carries a value', function () {
    $rows = [
        (object) ['value' => null],
        (object) ['value' => 'present'],
    ];

    expect(TableColumn::hasValues($rows, fn ($row) => $row->value))->toBeTrue();
});

test('hasValues treats a non-empty array as a value', function () {
    $rows = [(object) ['hints' => ['security']]];

    expect(TableColumn::hasValues($rows, fn ($row) => $row->hints))->toBeTrue();
});

test('hasValues treats an object value (e.g. a related model) as present', function () {
    $rows = [(object) ['run' => (object) ['status' => 'pass']]];

    expect(TableColumn::hasValues($rows, fn ($row) => $row->run))->toBeTrue();
});

test('hasValues is false for an empty row set', function () {
    expect(TableColumn::hasValues([], fn ($row) => $row->value))->toBeFalse();
});
