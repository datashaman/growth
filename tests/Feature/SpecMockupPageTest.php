<?php

use App\Models\Project;
use App\Models\Requirement;
use App\Models\Theme;
use App\Models\ThemeAssignment;
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
        $this->workItem,
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

it('uses assigned themes by default and supports non-persistent query preview overrides on the detail page', function () {
    $defaultTheme = Theme::create([
        'project_id' => $this->project->id,
        'name' => 'Mission Control',
        'slug' => 'mission-control',
        'is_default' => true,
    ]);
    $assignedTheme = Theme::create([
        'project_id' => $this->project->id,
        'name' => 'Checkout Warmth',
        'slug' => 'checkout-warmth',
        'css_tokens' => ['accent' => '#f97316'],
        'raw_css' => '.checkout-shell { color: var(--accent); }',
    ]);
    ThemeAssignment::create([
        'project_id' => $this->project->id,
        'theme_id' => $assignedTheme->id,
        'scope_type' => 'work_item',
        'scope_key' => $this->workItem->reference(),
    ]);

    $this->actingAs($this->user)
        ->get(route('mockups.show', $this->mockup))
        ->assertOk()
        ->assertSee('data-test="mockup-theme-selector"', false)
        ->assertSee('Mission Control')
        ->assertSee('Checkout Warmth')
        ->assertSee('data-assigned-theme="checkout-warmth"', false)
        ->assertSee('theme=checkout-warmth', false)
        ->assertDontSee('growth:project:${projectId}:mockup-theme', false)
        ->assertSee('data-src-base="'.route('mockups.raw', ['mockup' => $this->mockup, 'revision' => $this->mockup->currentRevision->id]).'"', false);

    $this->actingAs($this->user)
        ->get(route('mockups.show', ['mockup' => $this->mockup, 'theme' => $defaultTheme->slug]))
        ->assertOk()
        ->assertSee('data-current-theme="mission-control"', false)
        ->assertSee('theme=mission-control', false);

    $this->actingAs($this->user)
        ->withHeader('Sec-Fetch-Dest', 'iframe')
        ->get(route('mockups.raw', ['mockup' => $this->mockup, 'theme' => $assignedTheme->slug]))
        ->assertOk()
        ->assertSee('data-growth-theme="checkout-warmth"', false)
        ->assertSee('--accent: #f97316;', false)
        ->assertSee('.checkout-shell { color: var(--accent); }', false);
});

it('prefers mockup scoped theme assignments on the detail page', function () {
    $workItemTheme = Theme::create([
        'project_id' => $this->project->id,
        'name' => 'Checkout Warmth',
        'slug' => 'checkout-warmth',
    ]);
    $mockupTheme = Theme::create([
        'project_id' => $this->project->id,
        'name' => 'Buyer Market',
        'slug' => 'buyer-market',
        'raw_css' => '.hero { color: #123456; }',
    ]);
    ThemeAssignment::create([
        'project_id' => $this->project->id,
        'theme_id' => $workItemTheme->id,
        'scope_type' => 'work_item',
        'scope_key' => $this->workItem->reference(),
    ]);
    ThemeAssignment::create([
        'project_id' => $this->project->id,
        'theme_id' => $mockupTheme->id,
        'scope_type' => 'mockup',
        'scope_key' => $this->mockup->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('mockups.show', $this->mockup))
        ->assertOk()
        ->assertSee('data-assigned-theme="buyer-market"', false)
        ->assertSee('theme=buyer-market', false)
        ->assertDontSee('theme=checkout-warmth', false);

    $this->actingAs($this->user)
        ->withHeader('Sec-Fetch-Dest', 'iframe')
        ->get(route('mockups.raw', ['mockup' => $this->mockup, 'theme' => 'buyer-market']))
        ->assertOk()
        ->assertSee('data-growth-theme="buyer-market"', false)
        ->assertSee('.hero { color: #123456; }', false);
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
        ->and($csp)->toContain("navigate-to 'none'")
        ->and($csp)->toContain("frame-ancestors 'self'")
        ->and($csp)->toContain('sandbox allow-scripts')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('does not render the owner reference inside the mockup preview', function () {
    $this->mockup->currentRevision->forceFill([
        'html' => '<!doctype html><html><body><p>'.$this->workItem->reference().' · Checkout</p><h1>Checkout mockup</h1></body></html>',
    ])->save();

    $this->actingAs($this->user)
        ->withHeader('Sec-Fetch-Dest', 'iframe')
        ->get(route('mockups.raw', $this->mockup))
        ->assertOk()
        ->assertDontSee($this->workItem->reference(), false)
        ->assertSee('<p>Checkout</p>', false);
});

it('prevents raw mockup links and forms from navigating the preview frame', function () {
    $this->mockup->currentRevision->forceFill([
        'html' => '<!doctype html><html><body><a href="/login">Menu</a><form action="/login"><button>Submit</button></form></body></html>',
    ])->save();

    $this->actingAs($this->user)
        ->withHeader('Sec-Fetch-Dest', 'iframe')
        ->get(route('mockups.raw', $this->mockup))
        ->assertOk()
        ->assertSee('href="/login"', false)
        ->assertSee('data-growth-preview-inert', false)
        ->assertSee("target.closest('a[href]')", false)
        ->assertSee('event.preventDefault();', false);
});

it('injects selected theme css into raw mockup html without changing stored html', function () {
    $this->mockup->currentRevision->forceFill([
        'html' => '<!doctype html><html><head><style>body { background: hotpink; }</style></head><body><h1>Checkout mockup</h1></body></html>',
    ])->save();

    Theme::create([
        'project_id' => $this->project->id,
        'name' => 'Mission Control',
        'slug' => 'mission-control',
        'css_tokens' => ['--surface' => '#101418', 'accent color' => '#22c55e'],
        'raw_css' => 'body { background: var(--surface); color: var(--accent-color); }',
    ]);

    $response = $this->actingAs($this->user)
        ->withHeader('Sec-Fetch-Dest', 'iframe')
        ->get(route('mockups.raw', ['mockup' => $this->mockup, 'theme' => 'mission-control']))
        ->assertOk()
        ->assertSee('data-growth-theme="mission-control"', false)
        ->assertSee('--surface: #101418;', false)
        ->assertSee('--accent-color: #22c55e;', false)
        ->assertSee('body { background: var(--surface); color: var(--accent-color); }', false)
        ->assertSee('Checkout mockup', false);

    expect($response->getContent())->toMatch('/<style>body \{ background: hotpink; \}<\/style>.*<style data-growth-theme="mission-control">/s');

    expect($this->mockup->currentRevision->fresh()->html)
        ->not->toContain('data-growth-theme')
        ->and($this->mockup->currentRevision->fresh()->html)->toContain('Checkout mockup');

    $this->actingAs($this->user)
        ->withHeader('Sec-Fetch-Dest', 'iframe')
        ->get(route('mockups.raw', ['mockup' => $this->mockup, 'theme' => 'none']))
        ->assertOk()
        ->assertDontSee('data-growth-theme', false)
        ->assertDontSee('--surface: #101418;', false)
        ->assertDontSee('var(--accent-color)', false)
        ->assertSee('Checkout mockup', false);
});

it('ignores a theme slug from another project when serving raw mockup html', function () {
    $other = User::factory()->create();
    $otherProject = Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Other',
        'rigor_level' => 2,
    ]);
    Theme::create([
        'project_id' => $otherProject->id,
        'name' => 'Secret',
        'slug' => 'secret',
        'raw_css' => 'body { color: red; }',
    ]);

    $this->actingAs($this->user)
        ->withHeader('Sec-Fetch-Dest', 'iframe')
        ->get(route('mockups.raw', ['mockup' => $this->mockup, 'theme' => 'secret']))
        ->assertOk()
        ->assertDontSee('data-growth-theme', false)
        ->assertDontSee('color: red', false);
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

    // An empty revision parameter falls through the same way.
    $this->actingAs($this->user)
        ->withHeader('Sec-Fetch-Dest', 'iframe')
        ->get(route('mockups.raw', ['mockup' => $this->mockup, 'revision' => '']))
        ->assertOk()
        ->assertSee('Second cut', false);

    // A revision id that is not this mockup's 404s.
    $this->actingAs($this->user)
        ->withHeader('Sec-Fetch-Dest', 'iframe')
        ->get(route('mockups.raw', ['mockup' => $this->mockup, 'revision' => 'not-a-revision']))
        ->assertNotFound();

    // A real revision belonging to a sibling mockup is not served under this one.
    $sibling = createMockup(
        $this->workItem,
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
        ->assertSee("wire:key=\"mockup-{$this->mockup->id}-revision-{$first->id}\"", false)
        ->assertSee('revision='.$first->id);
});

it('keys the preview iframe by mockup and revision when sibling mockups have histories', function () {
    $this->mockup->appendRevision('<!doctype html><html><body><h1>First second cut</h1></body></html>');
    $firstMockupRevision = $this->mockup->revisions()->orderBy('number')->first();

    $sibling = createMockup(
        $this->workItem,
        'Compact layout',
        '<!doctype html><html><body><h1>Compact first cut</h1></body></html>',
    );
    $sibling->appendRevision('<!doctype html><html><body><h1>Compact second cut</h1></body></html>');
    $siblingFirstRevision = $sibling->revisions()->orderBy('number')->first();

    Livewire::test('pages::mockups.show', ['mockup' => $sibling])
        ->call('selectRevision', $siblingFirstRevision->id)
        ->assertSet('revisionId', $siblingFirstRevision->id)
        ->assertDontSee("wire:key=\"mockup-{$this->mockup->id}-revision-{$firstMockupRevision->id}\"", false)
        ->assertSee("wire:key=\"mockup-{$sibling->id}-revision-{$siblingFirstRevision->id}\"", false)
        ->assertSee(route('mockups.raw', ['mockup' => $sibling, 'revision' => $siblingFirstRevision->id]));
});

it('shows a mockup revision history', function () {
    $this->mockup->appendRevision('<!doctype html><html><body><h1>Second cut</h1></body></html>');

    $this->actingAs($this->user)
        ->get(route('mockups.show', $this->mockup))
        ->assertOk()
        ->assertSee('Revision 1')
        ->assertSee('Revision 2')
        ->assertSee('Revisions');
});

it('lists sibling mockups and links between them', function () {
    $sibling = createMockup(
        $this->workItem,
        'Compact layout',
        '<!doctype html><html><body><h1>Compact mockup</h1></body></html>',
    );

    $this->actingAs($this->user)
        ->get(route('mockups.show', $this->mockup))
        ->assertOk()
        // The selected mockup renders; its sibling is offered as a switch.
        ->assertDontSee('Named mockup')
        ->assertDontSee('A named variant with its own revision history')
        ->assertDontSee('Mockup variants')
        ->assertSee(route('mockups.raw', $this->mockup))
        ->assertSee('Compact layout')
        ->assertSee(route('mockups.show', $sibling));
});

it('orders the mockup selector with default first then named mockups', function () {
    createMockup(
        $this->workItem,
        'Zebra layout',
        '<!doctype html><html><body><h1>Zebra mockup</h1></body></html>',
    );
    createMockup(
        $this->workItem,
        'default',
        '<!doctype html><html><body><h1>Default mockup</h1></body></html>',
    );
    createMockup(
        $this->workItem,
        'Alpha layout',
        '<!doctype html><html><body><h1>Alpha mockup</h1></body></html>',
    );

    $component = Livewire::test('pages::mockups.show', ['mockup' => $this->mockup]);

    expect($component->get('mockup')->owner->mockups->pluck('name')->all())
        ->toBe(['default', 'Alpha layout', 'Checkout layout', 'Zebra layout']);
});

it('links to every mockup from the work item page', function () {
    $sibling = createMockup(
        $this->workItem,
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

it('renders a requirement-owned mockup and links it from the requirement page', function () {
    $requirement = Requirement::create([
        'project_id' => $this->project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The checkout must be one page',
    ]);
    $mockup = createMockup(
        $requirement,
        'One-page checkout',
        '<!doctype html><html><body><h1>One-page</h1></body></html>',
    );

    // The requirement page offers the mockup in its gallery.
    $this->actingAs($this->user)
        ->get(route('requirements.show', $requirement))
        ->assertOk()
        ->assertSee('Spec mockups')
        ->assertSee(route('mockups.show', $mockup));

    // The mockup page renders sandboxed and points back to the requirement.
    $this->actingAs($this->user)
        ->get(route('mockups.show', $mockup))
        ->assertOk()
        ->assertSee('One-page checkout')
        ->assertSee(route('requirements.show', $requirement))
        ->assertSee(route('mockups.raw', $mockup));
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
        $otherItem,
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

it('renders the show page for a project-owned design system mockup without crashing', function () {
    $mockup = createMockup(
        $this->project,
        'layout',
        '<!doctype html><html><body><div id="growth-content"></div></body></html>',
    );

    $this->actingAs($this->user)
        ->get(route('mockups.show', $mockup))
        ->assertOk()
        ->assertSee('layout')
        ->assertSee(route('mockups.raw', $mockup))
        ->assertSee(route('mockups'))   // back link points to mockups index
        ->assertSee('Back to mockups');
});
