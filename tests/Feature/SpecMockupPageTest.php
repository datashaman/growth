<?php

use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use Livewire\Livewire;

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
    $this->mockup = createMockup(
        $this->workItem->id,
        'Checkout layout',
        '<!doctype html><html><body><h1>Checkout mockup</h1></body></html>',
    );
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

it('serves a chosen revision and 404s an unknown one', function () {
    $this->mockup->appendRevision('<!doctype html><html><body><h1>Second cut</h1></body></html>');
    [$first, $second] = $this->mockup->revisions()->get()->all();

    $this->actingAs($this->user)
        ->withHeader('Sec-Fetch-Dest', 'iframe')
        ->get(route('mockups.raw', ['mockup' => $this->mockup, 'revision' => $first->id]))
        ->assertOk()
        ->assertSee('Checkout mockup', false);

    // No revision parameter falls through to the current (latest) revision.
    $this->actingAs($this->user)
        ->withHeader('Sec-Fetch-Dest', 'iframe')
        ->get(route('mockups.raw', $this->mockup))
        ->assertOk()
        ->assertSee('Second cut', false);

    // A revision id that is not this mockup's 404s.
    $this->actingAs($this->user)
        ->withHeader('Sec-Fetch-Dest', 'iframe')
        ->get(route('mockups.raw', ['mockup' => $this->mockup, 'revision' => 'not-a-revision']))
        ->assertNotFound();

    // A real revision belonging to a sibling mockup is not served under this one.
    $sibling = createMockup(
        $this->workItem->id,
        'Compact layout',
        '<!doctype html><html><body>compact</body></html>',
    );
    $this->actingAs($this->user)
        ->withHeader('Sec-Fetch-Dest', 'iframe')
        ->get(route('mockups.raw', ['mockup' => $this->mockup, 'revision' => $sibling->currentRevision->id]))
        ->assertNotFound();
});

it('switches the iframe to a chosen revision', function () {
    $this->mockup->appendRevision('<!doctype html><html><body><h1>Second cut</h1></body></html>');
    $first = $this->mockup->revisions()->orderBy('number')->first();

    Livewire::test('pages::mockups.show', ['mockup' => $this->mockup])
        ->call('selectRevision', $first->id)
        ->assertSet('revisionId', $first->id)
        ->assertSee('revision='.$first->id);
});

it('shows a mockup revision history', function () {
    $this->mockup->appendRevision('<!doctype html><html><body><h1>Second cut</h1></body></html>');

    $this->actingAs($this->user)
        ->get(route('mockups.show', $this->mockup))
        ->assertOk()
        ->assertSee('Revision 1')
        ->assertSee('Revision 2');
});

it('lists sibling mockups and links between them', function () {
    $sibling = createMockup(
        $this->workItem->id,
        'Compact layout',
        '<!doctype html><html><body><h1>Compact mockup</h1></body></html>',
    );

    $this->actingAs($this->user)
        ->get(route('mockups.show', $this->mockup))
        ->assertOk()
        // The selected mockup renders; its sibling is offered as a switch.
        ->assertSee(route('mockups.raw', $this->mockup))
        ->assertSee('Compact layout')
        ->assertSee(route('mockups.show', $sibling));
});

it('links to every mockup from the work item page', function () {
    $sibling = createMockup(
        $this->workItem->id,
        'Compact layout',
        '<!doctype html><html><body>compact</body></html>',
    );

    $this->actingAs($this->user)
        ->get(route('work-items.show', $this->workItem))
        ->assertOk()
        ->assertSee('Spec mockups')
        ->assertSee(route('mockups.show', $this->mockup))
        ->assertSee(route('mockups.show', $sibling));
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
    $otherMockup = createMockup(
        $otherItem->id,
        'Their layout',
        '<!doctype html><html><body>secret</body></html>',
    );

    $this->actingAs($this->user)
        ->get(route('mockups.show', $otherMockup))
        ->assertNotFound();
    $this->actingAs($this->user)
        ->get(route('mockups.raw', $otherMockup))
        ->assertNotFound();
});
