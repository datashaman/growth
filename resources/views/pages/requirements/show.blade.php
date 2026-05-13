<?php

use App\Models\Requirement;
use App\Support\BadgeVariant;
use Livewire\Component;

new class extends Component {
    public Requirement $requirement;

    public function mount(Requirement $requirement): void
    {
        $this->requirement = $requirement->load([
            'project',
            'parent',
            'children',
            'workItems',
            'testCases',
            'anomalies',
        ]);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-detail-page-header
        :title="\Illuminate\Support\Str::limit($requirement->text, 80)"
        back-route="capabilities"
        :back-label="__('Back to capabilities')">
        <x-slot:badges>
            <flux:badge :color="BadgeVariant::doc($requirement->doc)" size="sm">{{ strtoupper($requirement->doc) }}</flux:badge>
            <flux:badge color="zinc" size="sm">{{ str_replace('_', ' ', $requirement->type) }}</flux:badge>
            @if ($requirement->priority)
                <flux:badge :color="BadgeVariant::priority($requirement->priority)" size="sm">{{ $requirement->priority }}</flux:badge>
            @endif
        </x-slot:badges>
        <x-slot:description>
            {{ __('Requirement in project') }} <a href="{{ route('dashboard', ['project' => $requirement->project_id]) }}" class="underline">{{ $requirement->project->name }}</a>
        </x-slot:description>

        <x-slot:actions>
            <flux:button size="sm" icon="pencil-square" variant="primary" :href="route('requirements.edit', $requirement)" wire:navigate>{{ __('Edit') }}</flux:button>
            <flux:modal.trigger name="delete-requirement">
                <flux:button size="sm" icon="trash" variant="danger">{{ __('Delete') }}</flux:button>
            </flux:modal.trigger>
        </x-slot:actions>
    </x-detail-page-header>

    <livewire:pages::requirements.delete-modal :requirement-id="$requirement->id" :key="'delete-requirement-'.$requirement->id" />

    <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg" class="mb-3">{{ __('Statement') }}</flux:heading>
        <flux:text class="whitespace-pre-line">{{ $requirement->text }}</flux:text>
    </section>

    <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg" class="mb-3">{{ __('Properties') }}</flux:heading>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Source') }}</dt>
                <dd class="mt-0.5">{{ $requirement->source ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Parent') }}</dt>
                <dd class="mt-0.5">
                    @if ($requirement->parent)
                        <a href="{{ route('requirements.show', $requirement->parent) }}" wire:navigate class="underline">{{ \Illuminate\Support\Str::limit($requirement->parent->text, 60) }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            @if ($requirement->tags)
                <div class="sm:col-span-2">
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Tags') }}</dt>
                    <dd class="mt-0.5 flex flex-wrap gap-1">
                        @foreach ($requirement->tags as $tag)
                            <flux:badge color="zinc" size="sm">{{ $tag }}</flux:badge>
                        @endforeach
                    </dd>
                </div>
            @endif
        </dl>
    </section>

    @if ($requirement->rationale)
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-3">{{ __('Rationale') }}</flux:heading>
            <flux:text class="whitespace-pre-line">{{ $requirement->rationale }}</flux:text>
        </section>
    @endif

    @if ($requirement->acceptance_criteria)
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-3">{{ __('Acceptance criteria') }}</flux:heading>
            <ul class="list-disc space-y-1 pl-5">
                @foreach ((array) $requirement->acceptance_criteria as $criterion)
                    <li class="text-sm">{{ $criterion }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($requirement->children->isNotEmpty())
        <x-data-table
            :title="__('Children')"
            :count="$requirement->children->count()">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Requirement') }}</flux:table.column>
                    <flux:table.column>{{ __('Doc') }}</flux:table.column>
                    <flux:table.column>{{ __('Priority') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($requirement->children as $child)
                        <flux:table.row>
                            <flux:table.cell>
                                <a href="{{ route('requirements.show', $child) }}" wire:navigate class="hover:underline">{{ \Illuminate\Support\Str::limit($child->text, 80) }}</a>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::doc($child->doc)" size="sm">{{ strtoupper($child->doc) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::priority($child->priority)" size="sm">{{ $child->priority ?? '—' }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif

    @if ($requirement->workItems->isNotEmpty())
        <x-data-table
            :title="__('Linked work items')"
            :count="$requirement->workItems->count()">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Work item') }}</flux:table.column>
                    <flux:table.column>{{ __('Kind') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($requirement->workItems as $item)
                        <flux:table.row>
                            <flux:table.cell>
                                <a href="{{ route('work-items.show', $item) }}" wire:navigate class="font-medium hover:underline">{{ $item->name }}</a>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::workItemKind($item->kind)" size="sm">{{ str_replace('_', ' ', $item->kind) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::workItemStatus($item->status)" size="sm">{{ str_replace('_', ' ', $item->status) }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif

    @if ($requirement->testCases->isNotEmpty())
        <x-data-table
            :title="__('Test cases verifying this')"
            :count="$requirement->testCases->count()">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Case') }}</flux:table.column>
                    <flux:table.column>{{ __('Objective') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($requirement->testCases as $case)
                        <flux:table.row>
                            <flux:table.cell class="font-medium">{{ $case->name }}</flux:table.cell>
                            <flux:table.cell>{{ $case->objective ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif

    @if ($requirement->anomalies->isNotEmpty())
        <x-data-table
            :title="__('Affecting anomalies')"
            :count="$requirement->anomalies->count()">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Anomaly') }}</flux:table.column>
                    <flux:table.column>{{ __('Severity') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($requirement->anomalies as $anomaly)
                        <flux:table.row>
                            <flux:table.cell>
                                <a href="{{ route('anomalies.show', $anomaly) }}" wire:navigate class="font-medium hover:underline">{{ $anomaly->summary }}</a>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::anomalySeverity($anomaly->severity)" size="sm">{{ $anomaly->severity }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::anomalyStatus($anomaly->status)" size="sm">{{ $anomaly->status }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif
</div>
