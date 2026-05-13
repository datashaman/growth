<?php

use App\Growth\Assurance\ReadinessGateEvaluator;
use App\Growth\Execution\ImplementationStatusSummarizer;
use App\Growth\Plan\PlanCapacitySummarizer;
use App\Growth\Plan\ScheduleHealthSummarizer;
use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    public ?string $selectedProjectId = null;

    public function mount(): void
    {
        $fromQuery = (string) request()->query('project', '');

        $this->selectedProjectId = $fromQuery !== ''
            ? $fromQuery
            : Project::query()->orderBy('created_at')->value('id');
    }

    #[Computed]
    public function projects()
    {
        return Project::query()
            ->orderBy('created_at')
            ->get(['id', 'name', 'description', 'integrity_level']);
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
    public function schedule(): ?array
    {
        return $this->project ? app(ScheduleHealthSummarizer::class)->summarize($this->project) : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    #[Computed]
    public function implementation(): ?array
    {
        return $this->project ? app(ImplementationStatusSummarizer::class)->summarize($this->project) : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    #[Computed]
    public function capacity(): ?array
    {
        return $this->project ? app(PlanCapacitySummarizer::class)->summarize($this->project) : null;
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
            ['label' => 'Capabilities', 'value' => $project->requirements_count],
            ['label' => 'Architecture views', 'value' => $project->design_views_count],
            ['label' => 'Verification plans', 'value' => $project->test_plans_count],
            ['label' => 'Work items', 'value' => $project->work_items_count],
            ['label' => 'Changes', 'value' => $project->change_requests_count],
            ['label' => 'Reviews', 'value' => $project->reviews_count],
            ['label' => 'Releases', 'value' => $project->releases_count],
            ['label' => 'Deployments', 'value' => $project->deployments_count],
        ];
    }

    /*
     * Semantic colour vocabulary for Flux badges across the dashboard.
     *
     *   green  → terminal success         (done, deployed, gate pass)
     *   teal   → intermediate success     (validated — checks ok, not deployed)
     *   blue   → in-flight work           (in_progress, implemented)
     *   sky    → not yet started          (todo, planned)
     *   amber  → warning / attention      (gate warn, warning finding, done_without_evidence)
     *   red    → blocked / failing        (blocked, gate fail, error finding, blocked_by_checks)
     *   zinc   → neutral / inert          (cancelled, default)
     *
     * Categorical (non-status) hierarchy colours:
     *   purple → deliverable
     *   indigo → work_package
     *   zinc   → task
     *
     * Flux's free badge component only recognises literal Tailwind colour names,
     * not semantic aliases like "success" — anything else falls through to zinc.
     */

    public function gateVariant(string $status): string
    {
        return match ($status) {
            'pass' => 'green',
            'warn' => 'amber',
            'fail' => 'red',
            default => 'zinc',
        };
    }

    public function findingVariant(string $severity): string
    {
        return match ($severity) {
            'error' => 'red',
            'warning' => 'amber',
            default => 'zinc',
        };
    }

    public function workItemStatusVariant(string $status): string
    {
        return match ($status) {
            'done' => 'green',
            'in_progress' => 'blue',
            'blocked' => 'red',
            'todo' => 'sky',
            'cancelled' => 'zinc',
            default => 'zinc',
        };
    }

    public function workItemKindVariant(string $kind): string
    {
        return match ($kind) {
            'deliverable' => 'purple',
            'work_package' => 'indigo',
            default => 'zinc',
        };
    }

    public function implementationStateVariant(string $state): string
    {
        return match ($state) {
            'deployed' => 'green',
            'validated' => 'teal',
            'implemented' => 'blue',
            'blocked_by_checks' => 'red',
            'done_without_evidence' => 'amber',
            'planned' => 'sky',
            default => 'zinc',
        };
    }

    public function implementationStateLabel(string $state): string
    {
        return str_replace('_', ' ', $state);
    }

    public function formatHours(?float $hours): string
    {
        if ($hours === null || $hours === 0.0) {
            return '—';
        }

        return rtrim(rtrim(number_format($hours, 1, '.', ''), '0'), '.').'h';
    }

    public function formatCurrency(float $amount, ?string $currency = null): string
    {
        if ($amount === 0.0) {
            return '—';
        }

        $symbol = $currency ? $currency.' ' : '';

        return $symbol.number_format($amount, 2);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Project Dashboard') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Readiness, delivery, and schedule state for your Growth projects.') }}
                </flux:text>
            </div>
            <div class="sm:w-72">
                <flux:select wire:model.live="selectedProjectId" :placeholder="__('Select a project')">
                    @foreach ($this->projects as $option)
                        <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </header>

        @if ($this->projects->isEmpty())
            <flux:callout icon="information-circle">
                <flux:callout.heading>{{ __('No projects yet') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('Create a project via the Growth MCP server to populate this dashboard.') }}
                </flux:callout.text>
            </flux:callout>
        @elseif ($this->project === null)
            <flux:callout icon="cursor-arrow-rays">
                <flux:callout.heading>{{ __('Select a project') }}</flux:callout.heading>
                <flux:callout.text>{{ __('Pick a project from the dropdown to see its dashboard.') }}</flux:callout.text>
            </flux:callout>
        @else
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex flex-col gap-2">
                    <div class="flex items-center gap-2">
                        <flux:heading size="lg">{{ $this->project->name }}</flux:heading>
                        <flux:badge color="zinc" size="sm">{{ __('Rigor :level', ['level' => $this->project->integrity_level]) }}</flux:badge>
                    </div>
                    @if ($this->project->description)
                        <flux:text class="text-zinc-600 dark:text-zinc-400">{{ $this->project->description }}</flux:text>
                    @endif
                </div>
            </section>

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

            <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="mb-3 flex items-center justify-between">
                        <flux:heading size="lg">{{ __('Readiness') }}</flux:heading>
                        <flux:badge :color="$this->gateVariant($this->readiness['status'])">
                            {{ __(ucfirst($this->readiness['status'])) }}
                        </flux:badge>
                    </div>
                    @if (count($this->readiness['gates']) === 0)
                        <flux:text>{{ __('No readiness gates defined.') }}</flux:text>
                    @else
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>{{ __('Gate') }}</flux:table.column>
                                <flux:table.column>{{ __('Status') }}</flux:table.column>
                                <flux:table.column align="end">{{ __('Findings') }}</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($this->readiness['gates'] as $gate)
                                    <flux:table.row>
                                        <flux:table.cell>
                                            <div class="font-medium">{{ $gate['id'] }}</div>
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $gate['description'] }}</div>
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <flux:badge :color="$this->gateVariant($gate['status'])" size="sm">
                                                {{ ucfirst($gate['status']) }}
                                            </flux:badge>
                                        </flux:table.cell>
                                        <flux:table.cell align="end">
                                            {{ count($gate['findings']) }}
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    @endif
                </div>

                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="mb-3 flex items-center justify-between">
                        <flux:heading size="lg">{{ __('Schedule health') }}</flux:heading>
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ count($this->schedule['findings']) }} {{ __('findings') }}
                        </flux:text>
                    </div>
                    @if (count($this->schedule['findings']) === 0)
                        <flux:text>{{ __('No schedule issues found.') }}</flux:text>
                    @else
                        <ul class="space-y-2">
                            @foreach ($this->schedule['findings'] as $finding)
                                <li class="flex items-start gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                    <flux:badge :color="$this->findingVariant($finding['severity'])" size="sm">
                                        {{ ucfirst($finding['severity']) }}
                                    </flux:badge>
                                    <div class="flex-1">
                                        <div class="text-sm">{{ $finding['message'] }}</div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $finding['rule'] }}</div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="mb-3 flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Implementation') }}</flux:heading>
                    <div class="flex flex-wrap gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                        <span>{{ __(':n with evidence', ['n' => $this->implementation['summary']['with_delivery_evidence']]) }}</span>
                        <span>{{ __(':n deployed', ['n' => $this->implementation['summary']['deployed']]) }}</span>
                        <span>{{ __(':n done without evidence', ['n' => $this->implementation['summary']['done_without_delivery_evidence']]) }}</span>
                    </div>
                </div>
                @if (count($this->implementation['results']) === 0)
                    <flux:text>{{ __('No work items defined.') }}</flux:text>
                @else
                    <flux:table>
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
                                        <div class="font-medium">{{ $row['name'] }}</div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge :color="$this->workItemKindVariant($row['kind'])" size="sm">
                                            {{ str_replace('_', ' ', $row['kind']) }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge :color="$this->workItemStatusVariant($row['status'])" size="sm">
                                            {{ str_replace('_', ' ', $row['status']) }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge :color="$this->implementationStateVariant($row['implementation_state'])" size="sm">
                                            {{ $this->implementationStateLabel($row['implementation_state']) }}
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
                @endif
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="mb-3 flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Capacity') }}</flux:heading>
                    <div class="flex flex-wrap gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                        <span>{{ __('Est :h', ['h' => $this->formatHours($this->capacity['totals']['effort_estimate_hours'])]) }}</span>
                        <span>{{ __('Actual :h', ['h' => $this->formatHours($this->capacity['totals']['effort_actual_hours'])]) }}</span>
                        <span>{{ __('Cost est :c', ['c' => $this->formatCurrency($this->capacity['totals']['cost_estimate_amount'])]) }}</span>
                    </div>
                </div>
                @if (count($this->capacity['roles']) === 0)
                    <flux:text>{{ __('No roles or work items defined.') }}</flux:text>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Role') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Items') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Est hrs') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Actual hrs') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Cost est') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Cost actual') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Utilization') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->capacity['roles'] as $row)
                                <flux:table.row>
                                    <flux:table.cell>
                                        <div class="font-medium">{{ $row['role'] ?? __('Unassigned') }}</div>
                                        @if ($row['weekly_capacity_hours'])
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ __('Capacity :h/wk', ['h' => $this->formatHours($row['weekly_capacity_hours'])]) }}
                                            </div>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell align="end" class="tabular-nums">{{ $row['work_items'] }}</flux:table.cell>
                                    <flux:table.cell align="end" class="tabular-nums">{{ $this->formatHours($row['effort_estimate_hours']) }}</flux:table.cell>
                                    <flux:table.cell align="end" class="tabular-nums">{{ $this->formatHours($row['effort_actual_hours']) }}</flux:table.cell>
                                    <flux:table.cell align="end" class="tabular-nums">{{ $this->formatCurrency($row['cost_estimate_amount'], $row['rate_currency']) }}</flux:table.cell>
                                    <flux:table.cell align="end" class="tabular-nums">{{ $this->formatCurrency($row['cost_actual_amount'], $row['rate_currency']) }}</flux:table.cell>
                                    <flux:table.cell align="end" class="tabular-nums">
                                        @if ($row['utilization_estimate'] !== null)
                                            {{ number_format($row['utilization_estimate'] * 100, 0) }}%
                                        @else
                                            —
                                        @endif
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </section>
        @endif
</div>
