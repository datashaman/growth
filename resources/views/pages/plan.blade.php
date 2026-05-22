<?php

use App\Concerns\ProjectScoped;
use App\Models\WorkItem;
use App\Support\BadgeVariant;
use App\Support\EnumLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Plan')] class extends Component {
    use ProjectScoped;

    private const WORK_ITEM_BRANCH_LIMIT = 100;

    private const WORK_ITEM_VISIBLE_LIMIT = 500;

    /**
     * @var list<string>
     */
    public array $expandedWorkItemIds = [];

    /**
     * @return array<string,string>
     */
    public function getListeners(): array
    {
        return $this->projectScopedListeners();
    }

    public function onProjectDataChanged(): void
    {
        unset($this->workItemRows, $this->workItemCount, $this->milestones, $this->projectPlan);
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

    /**
     * Total work items in the selected project. This is counted separately so
     * the Plan page can report the full size without loading every item.
     */
    #[Computed]
    public function workItemCount(): int
    {
        return $this->selectedProject?->workItems()->count() ?? 0;
    }

    /**
     * Work items flattened into bounded WBS reading order. Only root items and
     * expanded direct children are loaded, with caps at both branch and page
     * levels so large projects do not render thousands of DOM rows.
     *
     * @return \Illuminate\Support\Collection<int,array{item:WorkItem,depth:int,has_more_siblings:bool,hidden_siblings:int,limit_reached:bool}>
     */
    #[Computed]
    public function workItemRows(): Collection
    {
        if ($this->selectedProject === null) {
            return collect();
        }

        $ordered = collect();
        $this->appendWorkItemRows($ordered, null, 0);

        return $ordered;
    }

    public function toggleWorkItem(string $workItemId): void
    {
        if (in_array($workItemId, $this->expandedWorkItemIds, true)) {
            $this->expandedWorkItemIds = array_values(array_diff($this->expandedWorkItemIds, [$workItemId]));
        } else {
            $this->expandedWorkItemIds[] = $workItemId;
        }

        unset($this->workItemRows);
    }

    public function workItemBranchLimit(): int
    {
        return self::WORK_ITEM_BRANCH_LIMIT;
    }

    private function appendWorkItemRows(Collection $ordered, ?string $parentId, int $depth): void
    {
        if ($ordered->count() >= self::WORK_ITEM_VISIBLE_LIMIT) {
            return;
        }

        $siblings = $this->workItemSiblings($parentId);
        $siblingCount = $this->workItemSiblingCount($parentId);
        $hasMoreSiblings = $siblingCount > self::WORK_ITEM_BRANCH_LIMIT;
        $visibleSiblings = $siblings->take(self::WORK_ITEM_BRANCH_LIMIT);

        foreach ($visibleSiblings as $index => $item) {
            if ($ordered->count() >= self::WORK_ITEM_VISIBLE_LIMIT) {
                $ordered->push([
                    'item' => $item,
                    'depth' => $depth,
                    'has_more_siblings' => false,
                    'hidden_siblings' => 0,
                    'limit_reached' => true,
                ]);

                return;
            }

            $ordered->push([
                'item' => $item,
                'depth' => $depth,
                'has_more_siblings' => $hasMoreSiblings && $index === $visibleSiblings->count() - 1,
                'hidden_siblings' => max(0, $siblingCount - self::WORK_ITEM_BRANCH_LIMIT),
                'limit_reached' => false,
            ]);

            if (in_array($item->id, $this->expandedWorkItemIds, true)) {
                $this->appendWorkItemRows($ordered, $item->id, $depth + 1);
            }
        }
    }

    /**
     * @return Collection<int,WorkItem>
     */
    private function workItemSiblings(?string $parentId): Collection
    {
        return $this->selectedProject
            ->workItems()
            ->with('responsibleRole')
            ->withCount('children')
            ->when(
                $parentId === null,
                fn (Builder $query) => $query->whereNull('parent_id'),
                fn (Builder $query) => $query->where('parent_id', $parentId),
            )
            ->orderByRaw("case status when 'in_progress' then 0 when 'blocked' then 1 when 'todo' then 2 when 'done' then 3 when 'cancelled' then 4 else 9 end")
            ->orderBy('name')
            ->limit(self::WORK_ITEM_BRANCH_LIMIT + 1)
            ->get();
    }

    private function workItemSiblingCount(?string $parentId): int
    {
        return $this->selectedProject
            ->workItems()
            ->when(
                $parentId === null,
                fn (Builder $query) => $query->whereNull('parent_id'),
                fn (Builder $query) => $query->where('parent_id', $parentId),
            )
            ->count();
    }

    #[Computed]
    public function roles()
    {
        return $this->selectedProject?->roles()->with('users')->orderBy('name')->get() ?? collect();
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
        :description="__('Milestones, work items, and roles delivering the project.')">
        @if ($this->selectedProject !== null && $this->projectPlan)
            <x-slot:actions>
                <flux:badge :color="BadgeVariant::planStatus($this->projectPlan->status)" size="sm">{{ EnumLabel::lower($this->projectPlan->status) }}</flux:badge>
            </x-slot:actions>
        @endif
    </x-project-page-header>

    @if ($this->selectedProject === null)
        <flux:callout icon="cursor-arrow-rays">
            <flux:callout.heading>{{ __('Select a project') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Pick a project to see its plan.') }}</flux:callout.text>
        </flux:callout>
    @else
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
            :count="$this->workItemCount"
            :count-label="__('items')"
            :empty="$this->workItemCount === 0"
            :empty-message="__('No work items defined.')">
            <flux:table class="[&_td]:align-middle [&_table]:table-fixed [&_th:first-child]:w-[52%] [&_th:nth-child(2)]:w-[16%] [&_th:nth-child(3)]:w-[14%] [&_th:nth-child(4)]:w-[18%]" data-test="work-item-tree-list">
                <flux:table.columns>
                    <flux:table.column data-test="work-item-tree-header">{{ __('Work item') }}</flux:table.column>
                    <flux:table.column>{{ __('Kind') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Role') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                @foreach ($this->workItemRows as $row)
                    @php($item = $row['item'])
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="grid min-w-0 grid-cols-[1.75rem_minmax(0,1fr)] items-center gap-2" @style(['padding-left: '.($row['depth'] * 1.5).'rem' => $row['depth'] > 0])>
                                @if ($item->children_count > 0)
                                    <button type="button" wire:click="toggleWorkItem('{{ $item->id }}')" class="flex h-5 w-5 shrink-0 items-center justify-center rounded border border-zinc-200 text-xs text-zinc-500 transition hover:border-zinc-400 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-400 dark:hover:border-zinc-500 dark:hover:text-zinc-100" aria-label="{{ in_array($item->id, $expandedWorkItemIds, true) ? __('Collapse :name', ['name' => $item->name]) : __('Expand :name', ['name' => $item->name]) }}" data-test="work-item-tree-toggle">
                                        {{ in_array($item->id, $expandedWorkItemIds, true) ? '−' : '+' }}
                                    </button>
                                @else
                                    <span aria-hidden="true" class="h-5 w-5 shrink-0"></span>
                                @endif

                                <a href="{{ route('work-items.show', $item) }}" wire:navigate class="flex min-w-0 items-center gap-2 hover:underline">
                                    <span class="w-fit shrink-0 rounded border border-zinc-200 bg-zinc-50 px-1.5 py-0.5 font-mono text-xs font-semibold text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-400">{{ $item->reference() }}</span>
                                    <span class="min-w-0 truncate font-medium text-zinc-900 dark:text-zinc-100">{{ $item->name }}</span>
                                </a>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="BadgeVariant::workItemKind($item->kind)" size="sm">{{ str_replace('_', ' ', $item->kind) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span title="{{ __('Workflow status set by the team. The Dashboard Implementation table shows the evidence-derived delivery State alongside it.') }}">
                                <flux:badge :color="BadgeVariant::workItemStatus($item->status)" size="sm">{{ str_replace('_', ' ', $item->status) }}</flux:badge>
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($item->responsibleRole)
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $item->responsibleRole->name }}</span>
                            @else
                                <span class="text-sm text-zinc-400 dark:text-zinc-500">—</span>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                    @if ($row['has_more_siblings'])
                        <flux:table.row>
                            <flux:table.cell>
                                <span class="text-sm text-zinc-500 dark:text-zinc-400" @style(['padding-left: '.(($row['depth'] + 1) * 1.5).'rem'])>
                                    {{ __('Showing first :limit at this level; :count more are not rendered.', ['limit' => $this->workItemBranchLimit(), 'count' => $row['hidden_siblings']]) }}
                                </span>
                            </flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                        </flux:table.row>
                    @endif
                    @if ($row['limit_reached'])
                        <flux:table.row>
                            <flux:table.cell>
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Display limit reached. Collapse branches or use MCP/API filters to inspect more work items.') }}
                                </span>
                            </flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                        </flux:table.row>
                        @break
                    @endif
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
                    <flux:table.column>{{ __('Users') }}</flux:table.column>
                    <flux:table.column>{{ __('Responsibilities') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->roles as $role)
                        <flux:table.row>
                            <flux:table.cell class="font-medium">{{ $role->name }}</flux:table.cell>
                            <flux:table.cell>{{ $role->users->pluck('name')->join(', ') ?: '—' }}</flux:table.cell>
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
