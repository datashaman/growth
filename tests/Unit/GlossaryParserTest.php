<?php

use App\Growth\Glossary\GlossaryParser;

it('parses a section, term, and body into one entry', function () {
    $entries = (new GlossaryParser)->parse(<<<'TXT'
    1.1

    work item

    A single unit of work.
    cf. milestone.
    TXT);

    expect($entries)->toHaveCount(1);
    expect($entries[0])->toBe([
        'section' => '1.1',
        'term' => 'work item',
        'body' => "A single unit of work.\ncf. milestone.",
    ]);
});

it('ignores preamble text before the first section', function () {
    $entries = (new GlossaryParser)->parse(<<<'TXT'
    Growth Domain Glossary

    Some introductory prose that is not an entry.

    2.3

    baseline

    A frozen snapshot.
    TXT);

    expect($entries)->toHaveCount(1);
    expect($entries[0]['term'])->toBe('baseline');
});

it('ends an entry body at the next section marker', function () {
    $entries = (new GlossaryParser)->parse(<<<'TXT'
    1.1

    first

    Body of the first entry.

    1.2

    second

    Body of the second entry.
    TXT);

    expect($entries)->toHaveCount(2);
    expect($entries[0]['body'])->toBe('Body of the first entry.');
    expect($entries[1]['body'])->toBe('Body of the second entry.');
});

it('returns no entries for text without any section markers', function () {
    expect((new GlossaryParser)->parse("just\nsome\nlines"))->toBe([]);
});

it('parses the committed glossary extract into well-formed entries', function () {
    $text = file_get_contents(dirname(__DIR__, 2).'/resources/glossary/glossary-extract.txt');
    $entries = (new GlossaryParser)->parse($text);

    expect(count($entries))->toBeGreaterThan(20);

    foreach ($entries as $entry) {
        expect($entry)->toHaveKeys(['section', 'term', 'body']);
        expect($entry['term'])->not->toBe('');
        expect($entry['body'])->not->toBe('');
    }

    $terms = array_column($entries, 'term');
    expect($terms)->toContain('work item', 'baseline', 'change request', 'readiness gate');
});
