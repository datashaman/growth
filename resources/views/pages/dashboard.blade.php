<?php

use App\Growth\Assurance\ReadinessGateEvaluator;
use App\Growth\Execution\ImplementationStatusSummarizer;
use App\Models\Anomaly;
use App\Models\ChangeRequest;
use App\Models\CustomViewpoint;
use App\Models\DesignView;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectPlan;
use App\Models\Requirement;
use App\Models\Review;
use App\Models\Risk;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\WorkItem;
use App\Models\WorkspaceMembership;
use App\Support\BadgeVariant;
use App\Support\EnumLabel;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    public ?string $selectedProjectId = null;

    public function mount(): void
    {
        $fromQuery = (string) request()->query('project', '');
        $fromSession = (string) session('selected_project_id', '');

        $this->selectedProjectId = match (true) {
            $fromQuery !== '' => $fromQuery,
            $fromSession !== '' => $fromSession,
            default => Project::query()->orderBy('created_at')->value('id'),
        };

        if ($this->selectedProjectId !== null && $this->selectedProjectId !== $fromSession) {
            session(['selected_project_id' => $this->selectedProjectId]);
        }
    }

    #[On('project-saved')]
    public function refreshProject(): void
    {
        unset($this->projects, $this->project);
    }

    /**
     * @return array<string,string>
     */
    public function getListeners(): array
    {
        $listeners = [];

        if ($this->selectedProjectId !== null) {
            $listeners['echo-private:projects.'.$this->selectedProjectId.',ProjectDataChanged'] = 'onProjectDataChanged';
        }

        return $listeners;
    }

    public function onProjectDataChanged(): void
    {
        unset(
            $this->project,
            $this->readiness,
            $this->implementation,
            $this->risks,
            $this->anomalies,
            $this->reviews,
            $this->countTiles,
            $this->findingSubjects,
        );
    }

    #[Computed]
    public function projects()
    {
        return Project::query()
            ->orderBy('created_at')
            ->get(['id', 'name', 'description', 'rigor_level']);
    }

    #[Computed]
    public function project(): ?Project
    {
        if ($this->selectedProjectId === null) {
            return null;
        }

        return Project::query()
            ->withCount([
                'stakeholders',
                'concerns',
                'requirements',
                'designViews',
                'testPlans',
                'workItems',
                'changeRequests',
                'reviews',
                'releases',
                'deployments',
            ])
            ->find($this->selectedProjectId);
    }

    /**
     * @return array<string,mixed>|null
     */
    #[Computed]
    public function readiness(): ?array
    {
        return $this->project ? app(ReadinessGateEvaluator::class)->evaluate($this->project) : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    #[Computed]
    public function implementation(): ?array
    {
        return $this->project ? app(ImplementationStatusSummarizer::class)->summarize($this->project) : null;
    }

    #[Computed]
    public function risks()
    {
        return $this->project
            ? $this->project->risks()->with('ownerRole')->orderBy('created_at', 'desc')->get()
            : collect();
    }

    #[Computed]
    public function anomalies()
    {
        return $this->project
            ? $this->project->anomalies()->orderBy('created_at', 'desc')->get()
            : collect();
    }

    #[Computed]
    public function reviews()
    {
        return $this->project
            ? $this->project->reviews()->orderBy('planned_at', 'desc')->get()
            : collect();
    }

    /**
     * @return array<int,array{label:string,value:int}>
     */
    #[Computed]
    public function countTiles(): array
    {
        $project = $this->project;

        if ($project === null) {
            return [];
        }

        return [
            ['label' => 'Stakeholders', 'value' => $project->stakeholders_count],
            ['label' => 'Concerns', 'value' => $project->concerns_count],
            ['label' => 'Requirements', 'value' => $project->requirements_count],
            ['label' => 'Architecture views', 'value' => $project->design_views_count],
            ['label' => 'Verification plans', 'value' => $project->test_plans_count],
            ['label' => 'Work items', 'value' => $project->work_items_count],
            ['label' => 'Changes', 'value' => $project->change_requests_count],
            ['label' => 'Reviews', 'value' => $project->reviews_count],
            ['label' => 'Releases', 'value' => $project->releases_count],
            ['label' => 'Deployments', 'value' => $project->deployments_count],
        ];
    }

    #[Computed]
    public function canMoveProject(): bool
    {
        $userId = auth()->id();
        $mutators = [WorkspaceMembership::ROLE_OWNER, WorkspaceMembership::ROLE_ADMIN];

        $sourceRole = WorkspaceMembership::query()
            ->where('user_id', $userId)
            ->where('workspace_id', auth()->user()->active_workspace_id)
            ->value('role');

        if (! in_array($sourceRole, $mutators, true)) {
            return false;
        }

        return WorkspaceMembership::query()
            ->where('user_id', $userId)
            ->whereIn('role', $mutators)
            ->where('workspace_id', '!=', auth()->user()->active_workspace_id)
            ->exists();
    }

    /**
     * Resolve readable labels (and optional routes) for every (subject_type, subject_id)
     * referenced by any readiness gate finding, in a single query per type.
     *
     * @return array<string,array{label:string,route:?string}>
     */
    #[Computed]
    public function findingSubjects(): array
    {
        if ($this->readiness === null) {
            return [];
        }

        $map = [
            'requirement' => [Requirement::class, 'text', 'requirements.show', null],
            'design_view' => [DesignView::class, 'name', null, 'architecture'],
            'custom_viewpoint' => [CustomViewpoint::class, 'name', null, 'architecture'],
            'test_plan' => [TestPlan::class, 'name', null, 'verification'],
            'test_case' => [TestCase::class, 'name', null, 'verification'],
            'anomaly' => [Anomaly::class, 'summary', 'anomalies.show', null],
            'milestone' => [Milestone::class, 'name', null, 'plan'],
            'work_item' => [WorkItem::class, 'name', 'work-items.show', null],
            'project_plan' => [ProjectPlan::class, 'name', null, 'plan'],
            'review' => [Review::class, 'title', 'reviews.show', null],
            'risk' => [Risk::class, 'title', 'risks.show', null],
            'change_request' => [ChangeRequest::class, 'title', 'change-requests.show', null],
        ];

        $gateFindings = collect($this->readiness['gates'] ?? [])
            ->flatMap(fn (array $gate) => $gate['findings']);

        $idsByType = $gateFindings
            ->filter(fn (array $f) => isset($f['subject_type'], $f['subject_id']) && isset($map[$f['subject_type']]))
            ->groupBy('subject_type')
            ->map(fn ($group) => $group->pluck('subject_id')->unique()->values()->all())
            ->all();

        $result = [];
        foreach ($idsByType as $type => $ids) {
            [$modelClass, $field, $routeName, $indexRouteName] = $map[$type];
            $fallbackRoute = $indexRouteName ? route($indexRouteName) : null;
            $modelClass::query()
                ->whereIn('id', $ids)
                ->get(['id', $field])
                ->each(function ($row) use (&$result, $type, $field, $routeName, $fallbackRoute): void {
                    $result[$type.':'.$row->id] = [
                        'label' => (string) str((string) $row->{$field})->limit(80),
                        'route' => $routeName ? route($routeName, $row->id) : $fallbackRoute,
                    ];
                });
        }

        return $result;
    }

}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
        <header class="flex flex-col gap-2">
            <flux:heading size="xl">{{ __('Project Dashboard') }}</flux:heading>
            <flux:text>{{ __('Readiness, delivery, and schedule state for your Growth projects.') }}</flux:text>
        </header>

        @if ($this->projects->isEmpty())
            <flux:callout icon="information-circle">
                <flux:callout.heading>{{ __('No projects yet') }}</flux:callout.heading>
                <flux:callout.text>{{ __('Create your first project to start tracking stakeholders, requirements, work, and evidence.') }}</flux:callout.text>
                <div class="mt-3">
                    <flux:modal.trigger name="create-project">
                        <flux:button icon="plus" variant="primary">{{ __('New project') }}</flux:button>
                    </flux:modal.trigger>
                </div>
            </flux:callout>
        @elseif ($this->project === null)
            <flux:callout icon="cursor-arrow-rays">
                <flux:callout.heading>{{ __('Select a project') }}</flux:callout.heading>
                <flux:callout.text>{{ __('Pick a project from the dropdown to see its dashboard.') }}</flux:callout.text>
            </flux:callout>
        @else
            @php
                $lens = auth()->user()->lens();
            @endphp
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">{{ $this->project->name }}</flux:heading>
                            <flux:badge color="zinc" size="sm">{{ __('Rigor :level', ['level' => $this->project->rigor_level]) }}</flux:badge>
                            <flux:badge :color="BadgeVariant::projectStatus($this->project->status)" size="sm">{{ EnumLabel::lower($this->project->status) }}</flux:badge>
                        </div>
                        @if ($this->project->description)
                            <flux:text class="text-zinc-600 dark:text-zinc-400">{{ $this->project->description }}</flux:text>
                        @endif
                    </div>
                    <div class="flex gap-1">
                        <flux:button size="sm" icon="pencil-square" variant="ghost" :tooltip="__('Edit project')"
                            wire:click="$dispatch('edit-project', { projectId: '{{ $this->project->id }}' })" />
                        @if ($this->canMoveProject)
                            <flux:button size="sm" icon="arrows-right-left" variant="ghost" :tooltip="__('Move to another workspace')"
                                wire:click="$dispatch('move-project', { projectId: '{{ $this->project->id }}' })"
                                data-test="move-project-button" />
                        @endif
                        <flux:button size="sm" icon="trash" variant="ghost" :tooltip="__('Delete project')"
                            wire:click="$dispatch('delete-project', { projectId: '{{ $this->project->id }}' })" />
                    </div>
                </div>
            </section>

            @if ($lens->revealsPanel('counts'))
            <section>
                <flux:heading size="lg" class="mb-3">{{ __('Counts') }}</flux:heading>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5">
                    @foreach ($this->countTiles as $tile)
                        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $tile['label'] }}</div>
                            <div class="mt-1 text-2xl font-semibold">{{ $tile['value'] }}</div>
                        </div>
                    @endforeach
                </div>
            </section>
            @endif

            @if ($lens->revealsPanel('readiness'))
            <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                @if ($lens->revealsPanel('readiness'))
                <x-data-table
                    :empty="count($this->readiness['gates']) === 0"
                    :empty-message="__('No readiness gates defined.')">
                    <x-slot:header>
                        <flux:heading size="lg">{{ __('Readiness') }}</flux:heading>
                        <flux:badge :color="BadgeVariant::gate($this->readiness['status'])">
                            {{ EnumLabel::lower($this->readiness['status']) }}
                        </flux:badge>
                    </x-slot:header>

                    <flux:table class="w-full table-fixed [&_td]:align-top [&_td]:break-words">
                        <flux:table.columns>
                            <flux:table.column>{{ __('Gate') }}</flux:table.column>
                            <flux:table.column class="w-24">{{ __('Status') }}</flux:table.column>
                            <flux:table.column align="end" class="w-28">{{ __('Findings') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows x-data="{ openGate: null }">
                            @foreach ($this->readiness['gates'] as $gate)
                                @php
                                    $hasFindings = count($gate['findings']) > 0;
                                    $gateId = $gate['id'];
                                    $toggleExpr = "openGate = openGate === '{$gateId}' ? null : '{$gateId}'";
                                @endphp
                                <flux:table.row
                                    @class(['cursor-pointer' => $hasFindings])
                                    x-on:click="{{ $hasFindings ? $toggleExpr : '' }}"
                                    x-on:keydown.enter.prevent="{{ $hasFindings ? $toggleExpr : '' }}"
                                    x-on:keydown.space.prevent="{{ $hasFindings ? $toggleExpr : '' }}"
                                    role="{{ $hasFindings ? 'button' : '' }}"
                                    tabindex="{{ $hasFindings ? '0' : '' }}"
                                    aria-controls="{{ $hasFindings ? 'gate-findings-'.$gateId : '' }}"
                                    x-bind:aria-expanded="openGate === '{{ $gateId }}' ? 'true' : 'false'"
                                >
                                    <flux:table.cell>
                                        <div class="font-medium">{{ EnumLabel::gate($gate['id']) }}</div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $gate['description'] }}</div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge :color="BadgeVariant::gate($gate['status'])" size="sm">
                                            {{ EnumLabel::lower($gate['status']) }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell align="end">
                                        <span class="inline-flex items-center gap-1">
                                            {{ count($gate['findings']) }}
                                            @if ($hasFindings)
                                                <flux:icon.chevron-down
                                                    class="size-4 transition-transform"
                                                    x-bind:class="openGate === '{{ $gate['id'] }}' ? 'rotate-180' : ''" />
                                            @endif
                                        </span>
                                    </flux:table.cell>
                                </flux:table.row>
                                @if ($hasFindings)
                                    <flux:table.row
                                        id="gate-findings-{{ $gate['id'] }}"
                                        x-show="openGate === '{{ $gate['id'] }}'"
                                        x-cloak
                                        class="whitespace-normal"
                                    >
                                        <flux:table.cell colspan="3" class="!ps-3 !pe-3 whitespace-normal">
                                            @php
                                                $grouped = collect($gate['findings'])->groupBy(fn ($f) => $f['rule'].'|'.$f['message']);
                                            @endphp
                                            <ul class="space-y-2">
                                                @foreach ($grouped as $group)
                                                    @php $head = $group->first(); @endphp
                                                    <li class="flex items-start gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                                        <div class="w-20 shrink-0">
                                                            <flux:badge :color="BadgeVariant::finding($head['severity'])" size="sm">
                                                                {{ EnumLabel::lower($head['severity']) }}
                                                            </flux:badge>
                                                        </div>
                                                        <div class="min-w-0 flex-1">
                                                            <div class="text-sm break-words">{{ $head['message'] }}</div>
                                                            <div class="text-xs text-zinc-500 dark:text-zinc-400 break-words">{{ EnumLabel::findingRule($head['rule']) }}</div>
                                                            @php
                                                                $subjects = $group
                                                                    ->filter(fn ($f) => isset($f['subject_type'], $f['subject_id']))
                                                                    ->map(fn ($f) => $this->findingSubjects[$f['subject_type'].':'.$f['subject_id']] ?? null)
                                                                    ->filter()
                                                                    ->unique(fn ($s) => $s['label'].'|'.($s['route'] ?? ''));
                                                            @endphp
                                                            @if ($subjects->isNotEmpty())
                                                                <ul class="mt-2 space-y-1">
                                                                    @foreach ($subjects as $subject)
                                                                        <li class="text-xs text-zinc-600 dark:text-zinc-300 break-words">
                                                                            @if ($subject['route'])
                                                                                <a href="{{ $subject['route'] }}" wire:navigate class="hover:underline">{{ $subject['label'] }}</a>
                                                                            @else
                                                                                {{ $subject['label'] }}
                                                                            @endif
                                                                        </li>
                                                                    @endforeach
                                                                </ul>
                                                            @endif
                                                        </div>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endif
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </x-data-table>
                @endif

            </section>
            @endif

            @if ($lens->revealsPanel('implementation'))
            <x-data-table
                :empty="count($this->implementation['results']) === 0"
                :empty-message="__('No work items defined.')">
                <x-slot:header>
                    <flux:heading size="lg">{{ __('Implementation') }}</flux:heading>
                    <div class="flex flex-wrap gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                        <span>{{ __(':n with evidence', ['n' => $this->implementation['summary']['with_delivery_evidence']]) }}</span>
                        <span>{{ __(':n deployed', ['n' => $this->implementation['summary']['deployed']]) }}</span>
                        <span>{{ __(':n done without evidence', ['n' => $this->implementation['summary']['done_without_delivery_evidence']]) }}</span>
                    </div>
                </x-slot:header>

                <flux:table class="[&_td]:align-top">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Work item') }}</flux:table.column>
                        <flux:table.column>{{ __('Kind') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('State') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Evidence') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Checks') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Deploys') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->implementation['results'] as $row)
                            <flux:table.row>
                                <flux:table.cell>
                                    <a href="{{ route('work-items.show', $row['id']) }}" wire:navigate class="font-medium hover:underline">{{ $row['name'] }}</a>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="BadgeVariant::workItemKind($row['kind'])" size="sm">
                                        {{ str_replace('_', ' ', $row['kind']) }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="BadgeVariant::workItemStatus($row['status'])" size="sm">
                                        {{ str_replace('_', ' ', $row['status']) }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="BadgeVariant::implementationState($row['implementation_state'])" size="sm">
                                        {{ str_replace('_', ' ', $row['implementation_state']) }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $row['delivery_links'] }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">
                                    @if ($row['failed_checks'] > 0)
                                        <span class="text-red-600 dark:text-red-400">{{ $row['failed_checks'] }} {{ __('fail') }}</span>
                                        @if ($row['successful_checks'] > 0) / @endif
                                    @endif
                                    @if ($row['successful_checks'] > 0)
                                        <span class="text-emerald-600 dark:text-emerald-400">{{ $row['successful_checks'] }} {{ __('ok') }}</span>
                                    @endif
                                    @if ($row['failed_checks'] === 0 && $row['successful_checks'] === 0)
                                        —
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">
                                    @if ($row['successful_deployments'] > 0)
                                        <span class="text-emerald-600 dark:text-emerald-400">{{ $row['successful_deployments'] }}</span>/{{ $row['deployments'] }}
                                    @elseif ($row['deployments'] > 0)
                                        {{ $row['deployments'] }}
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </x-data-table>
            @endif

            @if ($lens->revealsPanel('risks'))
            <x-data-table
                :title="__('Risks')"
                :count="$this->risks->count()"
                :count-label="__('tracked')"
                :empty="$this->risks->isEmpty()"
                :empty-message="__('No risks tracked.')">
                <x-slot:actions>
                    @if ($this->project)
                        <flux:modal.trigger name="create-risk">
                            <flux:button size="sm" icon="plus" variant="primary">{{ __('New risk') }}</flux:button>
                        </flux:modal.trigger>
                    @endif
                </x-slot:actions>
                <flux:table class="[&_td]:align-top">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Risk') }}</flux:table.column>
                        <flux:table.column>{{ __('Category') }}</flux:table.column>
                        <flux:table.column>{{ __('Exposure') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Owner') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->risks as $risk)
                            <flux:table.row>
                                <flux:table.cell>
                                    <a href="{{ route('risks.show', $risk) }}" wire:navigate class="font-medium hover:underline">{{ $risk->title }}</a>
                                    @if ($risk->description)
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ \Illuminate\Support\Str::limit($risk->description, 80) }}</div>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge color="zinc" size="sm">{{ str_replace('_', ' ', $risk->category) }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="BadgeVariant::riskExposure($risk->probability, $risk->impact)" size="sm">
                                        {{ BadgeVariant::riskExposureLabel($risk->probability, $risk->impact) }}
                                    </flux:badge>
                                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ __('Probability') }}: {{ $risk->probability ?? '—' }} · {{ __('Impact') }}: {{ $risk->impact ?? '—' }}
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="BadgeVariant::riskStatus($risk->status)" size="sm">
                                        {{ str_replace('_', ' ', $risk->status) }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>{{ $risk->ownerRole?->name ?? '—' }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </x-data-table>

            @if ($this->project)
                <livewire:pages::risks.create-modal :project-id="$this->project->id" :key="'create-risk-'.$this->project->id" />
            @endif
            @endif

            @if ($lens->revealsPanel('anomalies'))
            <x-data-table
                :title="__('Anomalies')"
                :count="$this->anomalies->where('status', '!=', 'closed')->count().' '.__('open').' / '.$this->anomalies->count()"
                :count-label="__('total')"
                :empty="$this->anomalies->isEmpty()"
                :empty-message="__('No anomalies reported.')">
                <flux:table class="[&_td]:align-top">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Anomaly') }}</flux:table.column>
                        <flux:table.column>{{ __('Severity') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Environment') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->anomalies as $anomaly)
                            <flux:table.row>
                                <flux:table.cell>
                                    <a href="{{ route('anomalies.show', $anomaly) }}" wire:navigate class="font-medium hover:underline">{{ $anomaly->summary }}</a>
                                    @if ($anomaly->description)
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ \Illuminate\Support\Str::limit($anomaly->description, 80) }}</div>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="BadgeVariant::anomalySeverity($anomaly->severity)" size="sm">
                                        {{ EnumLabel::lower($anomaly->severity) }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="BadgeVariant::anomalyStatus($anomaly->status)" size="sm">
                                        {{ EnumLabel::lower($anomaly->status) }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>{{ $anomaly->environment ?? '—' }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </x-data-table>
            @endif

            @if ($lens->revealsPanel('reviews'))
            <x-data-table
                :title="__('Reviews')"
                :count="$this->reviews->where('status', 'held')->count().' '.__('held').' / '.$this->reviews->count()"
                :count-label="__('total')"
                :empty="$this->reviews->isEmpty()"
                :empty-message="__('No reviews scheduled.')">
                <x-slot:actions>
                    @if ($this->project)
                        <flux:modal.trigger name="create-review">
                            <flux:button size="sm" icon="plus" variant="primary">{{ __('New review') }}</flux:button>
                        </flux:modal.trigger>
                    @endif
                </x-slot:actions>
                <flux:table class="[&_td]:align-top">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Review') }}</flux:table.column>
                        <flux:table.column>{{ __('Type') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Decision') }}</flux:table.column>
                        <flux:table.column>{{ __('When') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->reviews as $review)
                            <flux:table.row>
                                <flux:table.cell>
                                    <a href="{{ route('reviews.show', $review) }}" wire:navigate class="font-medium hover:underline">{{ $review->title }}</a>
                                    @if ($review->objective)
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ \Illuminate\Support\Str::limit($review->objective, 80) }}</div>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge color="zinc" size="sm">{{ str_replace('_', ' ', $review->type) }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="BadgeVariant::reviewStatus($review->status)" size="sm">
                                        {{ str_replace('_', ' ', $review->status) }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($review->decision)
                                        <flux:badge :color="BadgeVariant::reviewDecision($review->decision)" size="sm">
                                            {{ str_replace('_', ' ', $review->decision) }}
                                        </flux:badge>
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($review->held_at)
                                        {{ __('Held :date', ['date' => $review->held_at->format('Y-m-d')]) }}
                                    @elseif ($review->planned_at)
                                        {{ __('Planned :date', ['date' => $review->planned_at->format('Y-m-d')]) }}
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </x-data-table>

            @if ($this->project)
                <livewire:pages::reviews.create-modal :project-id="$this->project->id" :key="'create-review-'.$this->project->id" />
            @endif
            @endif
        @endif
</div>
