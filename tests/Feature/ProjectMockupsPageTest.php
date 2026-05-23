<?php

use App\Models\Project;
use App\Models\Theme;
use App\Models\ThemeAssignment;
use App\Models\User;
use App\Models\WorkItem;
use Database\Seeders\DemoProjectSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Mockup project',
        'rigor_level' => 2,
    ]);

    session(['selected_project_id' => $this->project->id]);
});

it('renders work-item mockups grouped by work item as preview strips', function () {
    $checkout = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'deliverable',
        'name' => 'Checkout',
        'needs_mockups' => true,
    ]);
    $history = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'deliverable',
        'name' => 'Order history',
        'needs_mockups' => true,
    ]);
    $checkoutMockup = createMockup($checkout, 'Checkout layout', '<!doctype html><html><body>checkout</body></html>');
    $historyMockup = createMockup($history, 'History layout', '<!doctype html><html><body>history</body></html>');

    $this->get(route('mockups'))
        ->assertOk()
        ->assertSee('Work-item mockups')
        ->assertSee($checkout->reference())
        ->assertSee('Checkout')
        ->assertSee('Checkout layout')
        ->assertSee(route('mockups.show', $checkoutMockup))
        ->assertSee(route('mockups.raw', $checkoutMockup))
        ->assertSee($history->reference())
        ->assertSee('Order history')
        ->assertSee('History layout')
        ->assertSee(route('mockups.show', $historyMockup))
        ->assertSee('data-test="project-mockup-groups"', false)
        ->assertSee('data-test="project-mockup-strip"', false)
        ->assertSee('<iframe', false);
});

it('uses assigned themes by default and supports non-persistent query preview overrides', function () {
    $defaultTheme = Theme::create([
        'project_id' => $this->project->id,
        'name' => 'Mission Control',
        'slug' => 'mission-control',
        'is_default' => true,
    ]);
    $checkoutTheme = Theme::create([
        'project_id' => $this->project->id,
        'name' => 'Checkout Warmth',
        'slug' => 'checkout-warmth',
    ]);

    $workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'deliverable',
        'name' => 'Checkout',
        'needs_mockups' => true,
    ]);
    $mockup = createMockup($workItem, 'Checkout layout', '<!doctype html><html><body>checkout</body></html>');
    ThemeAssignment::create([
        'project_id' => $this->project->id,
        'theme_id' => $checkoutTheme->id,
        'scope_type' => 'work_item',
        'scope_key' => $workItem->reference(),
    ]);

    $this->get(route('mockups'))
        ->assertOk()
        ->assertSee('data-test="mockup-theme-selector"', false)
        ->assertSee('Mission Control')
        ->assertSee('Checkout Warmth')
        ->assertSee('data-assigned-theme="checkout-warmth"', false)
        ->assertSee('theme=checkout-warmth', false)
        ->assertDontSee('growth:project:${projectId}:mockup-theme', false)
        ->assertSee('data-src-base="'.route('mockups.raw', $mockup).'"', false);

    $this->get(route('mockups', ['theme' => $defaultTheme->slug]))
        ->assertOk()
        ->assertSee('data-current-theme="mission-control"', false)
        ->assertSee('theme=mission-control', false);
});

it('orders project mockup cards the same way as the mockup selector', function () {
    $workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'deliverable',
        'name' => 'Checkout',
        'needs_mockups' => true,
    ]);
    $zebra = createMockup($workItem, 'Zebra layout', '<!doctype html><html><body>zebra</body></html>');
    $default = createMockup($workItem, 'default', '<!doctype html><html><body>default</body></html>');
    $alpha = createMockup($workItem, 'Alpha layout', '<!doctype html><html><body>alpha</body></html>');

    $this->get(route('mockups'))
        ->assertOk()
        ->assertSeeInOrder([
            route('mockups.show', $default),
            route('mockups.show', $alpha),
            route('mockups.show', $zebra),
        ]);
});

it('surfaces work items that need mockups but have none', function () {
    $missing = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'deliverable',
        'name' => 'Needs visual coverage',
        'needs_mockups' => true,
    ]);
    $covered = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'deliverable',
        'name' => 'Covered work',
        'needs_mockups' => true,
    ]);
    createMockup($covered, 'Covered layout', '<!doctype html><html></html>');

    $this->get(route('mockups'))
        ->assertOk()
        ->assertSee('Missing mockup coverage')
        ->assertSee($missing->reference())
        ->assertSee('Needs visual coverage')
        ->assertDontSee($covered->reference().' — Covered work', false);
});

it('shows an empty state when the project has no mockups', function () {
    WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'deliverable',
        'name' => 'Backend only',
    ]);

    $this->get(route('mockups'))
        ->assertOk()
        ->assertSee('No work-item mockups have been created for this project yet.');
});

it('does not render mockups from another workspace', function () {
    $other = User::factory()->create();
    $otherProject = Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Other project',
        'rigor_level' => 2,
    ]);
    $otherItem = WorkItem::create([
        'project_id' => $otherProject->id,
        'kind' => 'deliverable',
        'name' => 'Other checkout',
        'needs_mockups' => true,
    ]);
    createMockup($otherItem, 'Secret layout', '<!doctype html><html>secret</html>');

    $this->get(route('mockups'))
        ->assertOk()
        ->assertDontSee('Secret layout')
        ->assertDontSee('Other checkout');
});

it('includes the mockups page in the plan section navigation', function () {
    $this->get(route('dashboard', ['project' => $this->project->id]))
        ->assertOk()
        ->assertSee(route('mockups'));
});

it('refreshes project mockups when project data changes', function () {
    $workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'deliverable',
        'name' => 'Checkout',
        'needs_mockups' => true,
    ]);

    $component = Livewire::test('pages::mockups.index')
        ->assertSee('Missing mockup coverage')
        ->assertDontSee('Checkout layout');

    createMockup($workItem, 'Checkout layout', '<!doctype html><html></html>');

    $component->call('onProjectDataChanged')
        ->assertSee('Checkout layout')
        ->assertDontSee('Missing mockup coverage');
});

it('renders seeded demo mockups on the project mockups page', function () {
    $this->seed(DemoProjectSeeder::class);

    $project = Project::where('workspace_id', $this->user->active_workspace_id)
        ->where('name', 'Demo: Orbit Telemetry')
        ->firstOrFail();

    session(['selected_project_id' => $project->id]);

    $this->get(route('mockups'))
        ->assertOk()
        ->assertSee('Mission Control')
        ->assertSee('Solar Flare')
        ->assertSee('theme=mission-control', false)
        ->assertSee('Telemetry ingest dashboard')
        ->assertSee('Packet latency drilldown')
        ->assertSee('Failover drill console')
        ->assertSee('Missing mockup coverage')
        ->assertSee('Implement ingest stage 5');
});
