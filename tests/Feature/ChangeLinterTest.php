<?php

use App\Growth\Lint\ChangeLinter;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->projectWithUnlinkedChange = function (int $rigorLevel): Project {
        $project = Project::create([
            'workspace_id' => $this->user->active_workspace_id,
            'name' => "Rigor {$rigorLevel}",
            'rigor_level' => $rigorLevel,
        ]);
        ChangeRequest::create([
            'project_id' => $project->id,
            'title' => 'Unlinked change',
            'category' => 'scope',
            'priority' => 'medium',
            'status' => 'approved',
            'decision' => 'approved',
        ]);

        return $project;
    };
});

it('does not flag change.review.missing below rigor level 3', function (int $rigorLevel) {
    $project = ($this->projectWithUnlinkedChange)($rigorLevel);

    $rules = collect(app(ChangeLinter::class)->check($project))->pluck('rule');

    expect($rules)->not->toContain('change.review.missing');
})->with([1, 2]);

it('flags change.review.missing for an unlinked change at rigor level 3', function () {
    $project = ($this->projectWithUnlinkedChange)(3);

    $rules = collect(app(ChangeLinter::class)->check($project))->pluck('rule');

    expect($rules)->toContain('change.review.missing');
});
