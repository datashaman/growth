<?php

use App\Concerns\ProjectScoped;
use App\Support\BadgeVariant;
use App\Support\EnumLabel;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Plan')] class extends Component {
    use ProjectScoped;

    /**
     * @return array<string,string>
     */
    public function getListeners(): array
    {
        return $this->projectScopedListeners();
    }

    public function onProjectDataChanged(): void
    {
        unset($this->workItems, $this->milestones, $this->projectPlan);
    }

    #[Computed]
    public function projectPlan()
    {
        return $this->selectedProject?->projectPlan;
    }

    #[Computed]
    public function milestones()
    {
        return $this->selectedProject?->milestones()
            ->orderBy('name')
            ->get()
            ?? collect();
    }

    #[Computed]
    public function workItems()
    {
        return $this->selectedProject?->workItems()
            ->with('responsibleRole')
            ->orderBy('name')
            ->get()
            ?? collect();
    }

    #[Computed]
    public function roles()
    {
        return $this->selectedProject?->roles()->orderBy('name')->get() ?? collect();
    }

    #[Computed]
    public function baselines()
    {
        $plan = $this->selectedProject?->projectPlan;

        return $plan
            ? $plan->baselines()->with(['baselinedByUser', 'baselinedByAgent'])->orderByDesc('version')->get()
            : collect();
    }

}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-project-page-header
        :title="__('Plan')"
        :description="__('Milestones, work items, and roles delivering the project.')" />

    @if ($this->selectedProject === null)
        <flux:callout icon="cursor-arrow-rays">
            <flux:callout.heading>{{ __('Select a project') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Pick a project to see its plan.') }}</flux:callout.text>
        </flux:callout>
    @else
        @if ($this->projectPlan)
            <section class="flex items-center gap-2 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Project plan') }}</flux:heading>
                <flux:badge :color="BadgeVariant::planStatus($this->projectPlan->status)" size="sm">{{ EnumLabel::lower($this->projectPlan->status) }}</flux:badge>
            </section>
        @endif

        <x-data-table
            :title="__('Milestones')"
            :count="$this->milestones->count()"
            :count-label="__('planned')"
            :empty="$this->milestones->isEmpty()"
            :empty-message="__('No milestones defined.')">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Milestone') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Exit criteria') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->milestones as $milestone)
                        <flux:table.row>
                            <flux:table.cell class="font-medium">{{ $milestone->name }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::milestoneStatus($milestone->status)" size="sm">{{ EnumLabel::lower($milestone->status) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ \Illuminate\Support\Str::limit($milestone->exit_criteria ?? '—', 100) }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>

        <x-data-table
            :title="__('Work items')"
            :count="$this->workItems->count()"
            :count-label="__('items')"
            :empty="$this->workItems->isEmpty()"
            :empty-message="__('No work items defined.')">
            <x-slot:actions>
                <flux:button size="sm" icon="plus" variant="primary"
                    :href="route('work-items.create', ['project' => $this->selectedProject->id])" wire:navigate>
                    {{ __('New work item') }}
                </flux:button>
            </x-slot:actions>
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Work item') }}</flux:table.column>
                    <flux:table.column>{{ __('Kind') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Role') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->workItems as $item)
                        <flux:table.row>
                            <flux:table.cell>
                                <a href="{{ route('work-items.show', $item) }}" wire:navigate class="font-medium hover:underline">
                                    <span class="font-mono text-zinc-500 dark:text-zinc-400">{{ $item->reference() }}</span>
                                    {{ $item->name }}
                                </a>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::workItemKind($item->kind)" size="sm">{{ str_replace('_', ' ', $item->kind) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::workItemStatus($item->status)" size="sm">{{ str_replace('_', ' ', $item->status) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $item->responsibleRole?->name ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>

        <x-data-table
            :title="__('Roles')"
            :count="$this->roles->count()"
            :count-label="__('defined')"
            :empty="$this->roles->isEmpty()"
            :empty-message="__('No roles defined.')">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Role') }}</flux:table.column>
                    <flux:table.column>{{ __('Responsibilities') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->roles as $role)
                        <flux:table.row>
                            <flux:table.cell class="font-medium">{{ $role->name }}</flux:table.cell>
                            <flux:table.cell>{{ $role->responsibilities ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>

        <x-data-table
            :title="__('Baselines')"
            :count="$this->baselines->count()"
            :count-label="__('captured')"
            :empty="$this->baselines->isEmpty()"
            :empty-message="__('No baselines captured. Create one via the baseline-plan MCP tool.')">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column class="w-20">{{ __('Version') }}</flux:table.column>
                    <flux:table.column class="w-48">{{ __('Baselined') }}</flux:table.column>
                    <flux:table.column class="w-48">{{ __('By') }}</flux:table.column>
                    <flux:table.column>{{ __('Note') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->baselines as $baseline)
                        <flux:table.row>
                            <flux:table.cell class="font-medium tabular-nums">v{{ $baseline->version }}</flux:table.cell>
                            <flux:table.cell>{{ $baseline->baselined_at?->format('Y-m-d H:i') ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $baseline->baselinedByUser?->name ?? $baseline->baselinedByAgent?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell class="whitespace-normal break-words">{{ $baseline->note ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif
</div>
