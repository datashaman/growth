<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\GetMockup;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use App\Models\WorkItem;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Mockups',
        'rigor_level' => 2,
    ]);

    $this->workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'Ship it',
    ]);
});

it("returns the default mockup's raw html resource uris for a work item", function () {
    $mockup = createMockup($this->workItem, 'default', '<!doctype html><html><body>v1</body></html>');
    $mockup->appendRevision('<!doctype html><html><body>v2</body></html>');
    $revision = $mockup->fresh('currentRevision')->currentRevision;

    PlanningServer::tool(GetMockup::class, [
        'owner_type' => 'work_item',
        'owner_id' => $this->workItem->id,
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($mockup, $revision) {
            $json->where('id', $mockup->id)
                ->where('name', 'default')
                ->where('revision', 2)
                ->where('resources.mockup_uri', "growth://mockups/{$mockup->id}")
                ->where('resources.revision_uri', "growth://mockups/{$mockup->id}/{$revision->id}")
                ->where('resources.html_uri', "growth://mockups/{$mockup->id}/{$revision->id}/html")
                ->where('resources.preview_uri', "growth://mockups/{$mockup->id}/{$revision->id}/preview")
                ->where('resources.screenshot_asset.mime_type', 'image/png')
                ->where('resources.screenshot_asset.theme', 'assigned')
                ->where('resources.screenshot_asset.url', fn (string $url): bool => str_contains($url, "/mockups/{$mockup->id}/revisions/{$revision->id}/screenshot.png"))
                ->missing('html')
                ->etc();
        });
});

it('returns a named alternative when name is supplied', function () {
    createMockup($this->workItem, 'default', '<!doctype html><html><body>default</body></html>');
    $compact = createMockup($this->workItem, 'Compact layout', '<!doctype html><html><body>compact</body></html>');

    PlanningServer::tool(GetMockup::class, [
        'owner_type' => 'work_item',
        'owner_id' => $this->workItem->id,
        'name' => 'Compact layout',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($compact) {
            $json->where('id', $compact->id)
                ->where('name', 'Compact layout')
                ->where('resources.mockup_uri', "growth://mockups/{$compact->id}")
                ->where('resources.html_uri', "growth://mockups/{$compact->id}/{$compact->currentRevision->id}/html")
                ->where('resources.preview_uri', "growth://mockups/{$compact->id}/{$compact->currentRevision->id}/preview")
                ->where('resources.screenshot_asset.mime_type', 'image/png')
                ->where('resources.screenshot_asset.url', fn (string $url): bool => str_contains($url, "/mockups/{$compact->id}/revisions/{$compact->currentRevision->id}/screenshot.png"))
                ->missing('html')
                ->etc();
        });
});

it('returns an error when no mockup matches the requested name', function () {
    createMockup($this->workItem, 'default', '<!doctype html><html><body>default</body></html>');

    PlanningServer::tool(GetMockup::class, [
        'owner_type' => 'work_item',
        'owner_id' => $this->workItem->id,
        'name' => 'Nonexistent',
    ])->assertHasErrors(['No mockup named [Nonexistent]']);
});

it('returns an error when the work item has no default mockup', function () {
    PlanningServer::tool(GetMockup::class, [
        'owner_type' => 'work_item',
        'owner_id' => $this->workItem->id,
    ])->assertHasErrors(['No mockup named [default]']);
});

it("returns a requirement's default mockup", function () {
    $requirement = Requirement::create([
        'project_id' => $this->project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The checkout must be one page',
    ]);
    createMockup($requirement, 'default', '<!doctype html><html><body>one</body></html>');

    PlanningServer::tool(GetMockup::class, [
        'owner_type' => 'requirement',
        'owner_id' => $requirement->id,
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($requirement) {
            $json->where('owner_type', 'requirement')
                ->where('owner_id', $requirement->id)
                ->where('resources.mockup_uri', fn (string $uri): bool => str_starts_with($uri, 'growth://mockups/'))
                ->where('resources.html_uri', fn (string $uri): bool => str_ends_with($uri, '/html'))
                ->where('resources.preview_uri', fn (string $uri): bool => str_ends_with($uri, '/preview'))
                ->where('resources.screenshot_asset.mime_type', 'image/png')
                ->missing('html')
                ->etc();
        });
});

it('rejects an owner from another workspace', function () {
    $other = User::factory()->create();
    $otherProject = Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Theirs',
        'rigor_level' => 2,
    ]);
    $otherItem = WorkItem::create([
        'project_id' => $otherProject->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'Theirs',
    ]);
    createMockup($otherItem, 'default', '<!doctype html><html><body>secret</body></html>');

    PlanningServer::tool(GetMockup::class, [
        'owner_type' => 'work_item',
        'owner_id' => $otherItem->id,
    ])->assertHasErrors();
});
