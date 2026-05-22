<?php

/*
 * Requirements carry a short, sayable per-document reference (e.g. "SRS-001")
 * instead of a sentence-derived slug. Numbers are sequential within each
 * document tier of a project.
 */

use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 3,
    ]);
});

function makeRequirement(Project $project, string $doc, string $text): Requirement
{
    return $project->requirements()->create([
        'doc' => $doc,
        'type' => 'functional',
        'text' => $text,
    ]);
}

test('requirements get a sequential per-document number', function () {
    $first = makeRequirement($this->project, 'srs', 'First.');
    $second = makeRequirement($this->project, 'srs', 'Second.');

    expect($first->number)->toBe(1)
        ->and($second->number)->toBe(2);
});

test('each document tier numbers independently', function () {
    $srs = makeRequirement($this->project, 'srs', 'Software requirement.');
    $syrs = makeRequirement($this->project, 'syrs', 'System requirement.');

    expect($srs->number)->toBe(1)
        ->and($syrs->number)->toBe(1);
});

test('numbering is independent per project', function () {
    $other = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Mars Lander',
        'rigor_level' => 2,
    ]);

    makeRequirement($this->project, 'srs', 'On Lunar.');
    $onMars = makeRequirement($other, 'srs', 'On Mars.');

    expect($onMars->number)->toBe(1);
});

test('reference formats the document tier and zero-padded number', function () {
    $requirement = makeRequirement($this->project, 'srs', 'A requirement.');

    expect($requirement->reference())->toBe('SRS-001');
});

test('moving a requirement to another document tier re-numbers it within that tier', function () {
    makeRequirement($this->project, 'syrs', 'Existing system requirement.');
    $requirement = makeRequirement($this->project, 'srs', 'Promotable requirement.');

    expect($requirement->reference())->toBe('SRS-001');

    $requirement->update(['doc' => 'syrs']);

    expect($requirement->fresh()->reference())->toBe('SYRS-002');
});
