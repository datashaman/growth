<?php

use App\Concerns\ProjectScoped;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Plan')] class extends Component {
    use ProjectScoped;

    #[Computed]
    public function milestones()
    {
        return $this->selectedProject?->milestones()
            ->orderBy('target_date')
            ->get()
            ?? collect();
    }

    #[Computed]
    public function workItems()
    {
        return $this->selectedProject?->workItems()
            ->with('responsibleRole')
            ->orderBy('due_date')
            ->orderBy('name')
            ->get()
            ?? collect();
    }

    #[Computed]
    public function roles()
    {
        return $this->selectedProject?->roles()->orderBy('name')->get() ?? collect();
    }

    public function milestoneStatusVariant(string $status): string
    {
        return match ($status) {
            'pending' => 'sky',
            'hit' => 'green',
            'missed' => 'red',
            'deferred' => 'zinc',
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

    public function formatHours(?float $hours): string
    {
        if ($hours === null || $hours === 0.0) {
            return '—';
        }

        return rtrim(rtrim(number_format($hours, 1, '.', ''), '0'), '.').'h';
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-project-page-header
        :title="__('Plan')"
        :description="__('Milestones, work items, and roles delivering the project.')"
        :options="$this->projectOptions" />

    @if ($this->selectedProject === null)
        <flux:callout icon="cursor-arrow-rays">
            <flux:callout.heading>{{ __('Select a project') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Pick a project to see its plan.') }}</flux:callout.text>
        </flux:callout>
    @else
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Milestones') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $this->milestones->count() }} {{ __('planned') }}</flux:text>
            </div>
            @if ($this->milestones->isEmpty())
                <flux:text>{{ __('No milestones defined.') }}</flux:text>
            @else
                <flux:table class="[&_td]:align-top">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Milestone') }}</flux:table.column>
                        <flux:table.column>{{ __('Target') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Exit criteria') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->milestones as $milestone)
                            <flux:table.row>
                                <flux:table.cell class="font-medium">{{ $milestone->name }}</flux:table.cell>
                                <flux:table.cell>{{ $milestone->target_date?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$this->milestoneStatusVariant($milestone->status)" size="sm">{{ $milestone->status }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>{{ \Illuminate\Support\Str::limit($milestone->exit_criteria ?? '—', 100) }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Work items') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $this->workItems->count() }} {{ __('items') }}</flux:text>
            </div>
            @if ($this->workItems->isEmpty())
                <flux:text>{{ __('No work items defined.') }}</flux:text>
            @else
                <flux:table class="[&_td]:align-top">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Work item') }}</flux:table.column>
                        <flux:table.column>{{ __('Kind') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Role') }}</flux:table.column>
                        <flux:table.column>{{ __('Due') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Est') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Actual') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->workItems as $item)
                            <flux:table.row>
                                <flux:table.cell class="font-medium">{{ $item->name }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$this->workItemKindVariant($item->kind)" size="sm">{{ str_replace('_', ' ', $item->kind) }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$this->workItemStatusVariant($item->status)" size="sm">{{ str_replace('_', ' ', $item->status) }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>{{ $item->responsibleRole?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $item->due_date?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $this->formatHours((float) ($item->effort_estimate_hours ?? 0)) }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $this->formatHours((float) ($item->effort_actual_hours ?? 0)) }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Roles') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $this->roles->count() }} {{ __('defined') }}</flux:text>
            </div>
            @if ($this->roles->isEmpty())
                <flux:text>{{ __('No roles defined.') }}</flux:text>
            @else
                <flux:table class="[&_td]:align-top">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Role') }}</flux:table.column>
                        <flux:table.column>{{ __('Responsibilities') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Weekly capacity') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Hourly rate') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->roles as $role)
                            <flux:table.row>
                                <flux:table.cell class="font-medium">{{ $role->name }}</flux:table.cell>
                                <flux:table.cell>{{ $role->responsibilities ?? '—' }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $this->formatHours((float) ($role->weekly_capacity_hours ?? 0)) }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">
                                    @if ($role->hourly_rate_amount)
                                        {{ $role->rate_currency }} {{ number_format((float) $role->hourly_rate_amount, 2) }}
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
