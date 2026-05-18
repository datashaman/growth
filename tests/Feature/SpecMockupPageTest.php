<?php

use App\Models\Project;
use App\Models\SpecMockup;
use App\Models\User;
use App\Models\WorkItem;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Mockups',
        'rigor_level' => 2,
    ]);
    $this->workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'Checkout',
    ]);
    $this->mockup = SpecMockup::create([
        'work_item_id' => $this->workItem->id,
        'name' => 'Checkout layout',
        'html' => '<!doctype html><html><body><h1>Checkout mockup</h1></body></html>',
    ]);
});

it('renders the mockup inside a sandboxed iframe with no same-origin access', function () {
    $response = $this->actingAs($this->user)
        ->get(route('mockups.show', $this->mockup))
        ->assertOk()
        ->assertSee('Checkout layout');

    $body = $response->getContent();

    // Isolate the iframe tag so the assertion can't be satisfied by stray
    // markup elsewhere on the page.
    expect($body)->toMatch('/<iframe\b[^>]*>/');
    preg_match('/<iframe\b[^>]*>/', $body, $iframe);

    // The iframe is sandboxed, and crucially never granted allow-same-origin —
    // the mockup document runs in an opaque origin, walled off from Growth.
    expect($iframe[0])->toContain('sandbox="allow-scripts"')
        ->and($iframe[0])->not->toContain('allow-same-origin')
        ->and($iframe[0])->toContain(route('mockups.raw', $this->mockup));
});

it('serves the raw mockup HTML under a locked-down content security policy', function () {
    $response = $this->actingAs($this->user)
        ->withHeader('Sec-Fetch-Dest', 'iframe')
        ->get(route('mockups.raw', $this->mockup))
        ->assertOk()
        ->assertSee('Checkout mockup', false);

    $csp = $response->headers->get('Content-Security-Policy');

    // connect-src and form-action denied: the mockup has no channel to phone
    // home. frame-ancestors 'self': only Growth may embed it. The sandbox
    // directive sandboxes the document from the response itself.
    expect($csp)->toContain("connect-src 'none'")
        ->and($csp)->toContain("form-action 'none'")
        ->and($csp)->toContain("frame-ancestors 'self'")
        ->and($csp)->toContain('sandbox allow-scripts')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('bounces a top-level navigation to the raw route back to the wrapper page', function () {
    // Reached as a top-level navigation the agent HTML would run unsandboxed
    // in Growth's own origin, so a non-iframe fetch is redirected and the
    // mockup markup is never served as a document.
    $response = $this->actingAs($this->user)
        ->withHeader('Sec-Fetch-Dest', 'document')
        ->get(route('mockups.raw', $this->mockup));

    $response->assertRedirect(route('mockups.show', $this->mockup));
    expect($response->getContent())->not->toContain('Checkout mockup');
});

it('links to the mockup from the work item page', function () {
    $this->actingAs($this->user)
        ->get(route('work-items.show', $this->workItem))
        ->assertOk()
        ->assertSee('Spec mockup')
        ->assertSee(route('mockups.show', $this->mockup));
});

it('does not serve a mockup from another workspace', function () {
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
    $otherMockup = SpecMockup::create([
        'work_item_id' => $otherItem->id,
        'name' => 'Their layout',
        'html' => '<!doctype html><html><body>secret</body></html>',
    ]);

    $this->actingAs($this->user)
        ->get(route('mockups.show', $otherMockup))
        ->assertNotFound();
    $this->actingAs($this->user)
        ->get(route('mockups.raw', $otherMockup))
        ->assertNotFound();
});
