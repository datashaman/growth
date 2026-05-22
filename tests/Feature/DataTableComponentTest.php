<?php

use Illuminate\Support\Facades\Blade;

test('data table headers keep count filters and actions in a consistent non wrapping layout', function () {
    $html = Blade::render(<<<'BLADE'
        <x-data-table title="Items" :count="12" count-label="elements">
            <x-slot:filters><button>Filter</button></x-slot:filters>
            <x-slot:actions><button>Action</button></x-slot:actions>
            <div>Rows</div>
        </x-data-table>
    BLADE);

    expect($html)
        ->toContain('Items')
        ->toContain('12 elements')
        ->toContain('Filter')
        ->toContain('Action')
        ->toContain('data-table-count')
        ->toContain('whitespace-nowrap')
        ->toContain('[&_tbody_tr:hover]:bg-zinc-50');
});

test('custom data table headers can mark count text as non wrapping', function () {
    $html = Blade::render(<<<'BLADE'
        <x-data-table>
            <x-slot:header>
                <div>Verification</div>
                <div class="data-table-count">10 cases</div>
            </x-slot:header>
            <div>Rows</div>
        </x-data-table>
    BLADE);

    expect($html)
        ->toContain('10 cases')
        ->toContain('data-table-count')
        ->toContain('[&_.data-table-count]:shrink-0')
        ->toContain('[&_.data-table-count]:whitespace-nowrap');
});

test('sortable columns provide a reusable livewire sorting trigger', function () {
    $html = Blade::render(<<<'BLADE'
        <x-sortable-column label="When" field="created_at" sort="created_at" direction="desc" />
    BLADE);

    expect($html)
        ->toContain('wire:click="sortBy(\'created_at\')"')
        ->toContain('When')
        ->toContain('v');
});
