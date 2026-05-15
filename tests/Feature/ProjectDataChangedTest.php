<?php

use App\Events\ProjectDataChanged;
use App\Models\Anomaly;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\Risk;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
});

test('saving an Anomaly dispatches ProjectDataChanged on the project channel', function () {
    Event::fake([ProjectDataChanged::class]);

    Anomaly::create([
        'project_id' => $this->project->id,
        'summary' => 'Telemetry drift',
        'description' => 'Subsecond drift between burst windows.',
        'severity' => 'high',
        'status' => 'open',
    ]);

    Event::assertDispatched(
        ProjectDataChanged::class,
        function (ProjectDataChanged $event): bool {
            $channels = $event->broadcastOn();

            return $event->projectId === (string) $this->project->id
                && count($channels) === 1
                && $channels[0] instanceof PrivateChannel
                && $channels[0]->name === 'private-projects.'.$this->project->id;
        },
    );
});

test('deleting an Anomaly dispatches ProjectDataChanged', function () {
    $anomaly = Anomaly::create([
        'project_id' => $this->project->id,
        'summary' => 'Stale heartbeat',
        'description' => 'Missing heartbeat for 30s window.',
        'severity' => 'low',
        'status' => 'open',
    ]);

    Event::fake([ProjectDataChanged::class]);

    $anomaly->delete();

    Event::assertDispatched(ProjectDataChanged::class);
});

test('saves on every covered model dispatch the event', function () {
    Event::fake([ProjectDataChanged::class]);

    ChangeRequest::create([
        'project_id' => $this->project->id,
        'title' => 'Adjust orbit',
        'category' => 'scope',
        'status' => 'proposed',
    ]);

    WorkItem::create([
        'project_id' => $this->project->id,
        'name' => 'Recalibrate sensors',
        'kind' => 'task',
        'status' => 'todo',
    ]);

    Risk::create([
        'project_id' => $this->project->id,
        'title' => 'Solar flare interference',
        'category' => 'external',
        'probability' => 'medium',
        'impact' => 'medium',
        'status' => 'identified',
    ]);

    Event::assertDispatchedTimes(ProjectDataChanged::class, 3);
});
