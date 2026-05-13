<?php

use App\Concerns\ProjectScoped;
use App\Models\WorkItemDeliveryLink;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Evidence')] class extends Component {
    use ProjectScoped;

    #[Computed]
    public function releases()
    {
        return $this->selectedProject?->releases()
            ->orderByRaw('released_at IS NULL')
            ->orderByDesc('released_at')
            ->orderBy('version')
            ->get()
            ?? collect();
    }

    #[Computed]
    public function deployments()
    {
        return $this->selectedProject?->deployments()
            ->with('release')
            ->orderByRaw('deployed_at IS NULL')
            ->orderByDesc('deployed_at')
            ->get()
            ?? collect();
    }

    #[Computed]
    public function deliveryLinks()
    {
        if ($this->selectedProject === null) {
            return collect();
        }

        return WorkItemDeliveryLink::query()
            ->whereHas('workItem', fn ($q) => $q->where('project_id', $this->selectedProject->id))
            ->with(['workItem', 'checkRuns'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function releaseStatusVariant(string $status): string
    {
        return match ($status) {
            'released' => 'green',
            'candidate' => 'blue',
            'planned' => 'sky',
            'cancelled' => 'zinc',
            default => 'zinc',
        };
    }

    public function deploymentStatusVariant(string $status): string
    {
        return match ($status) {
            'succeeded' => 'green',
            'in_progress' => 'blue',
            'planned' => 'sky',
            'failed' => 'red',
            'rolled_back' => 'amber',
            'cancelled' => 'zinc',
            default => 'zinc',
        };
    }

    public function deliveryTypeVariant(string $type): string
    {
        return match ($type) {
            'pull_request' => 'indigo',
            'commit' => 'blue',
            'branch' => 'sky',
            default => 'zinc',
        };
    }

    public function checkConclusionVariant(?string $conclusion): string
    {
        return match ($conclusion) {
            'success' => 'green',
            'failure', 'timed_out', 'action_required' => 'red',
            'cancelled' => 'amber',
            'skipped', 'neutral' => 'zinc',
            default => 'sky',
        };
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-project-page-header
        :title="__('Evidence')"
        :description="__('Releases, deployments, and the delivery artefacts that back them.')"
        :options="$this->projectOptions" />

    @if ($this->selectedProject === null)
        <flux:callout icon="cursor-arrow-rays">
            <flux:callout.heading>{{ __('Select a project') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Pick a project to see its evidence trail.') }}</flux:callout.text>
        </flux:callout>
    @else
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Releases') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $this->releases->count() }} {{ __('total') }}</flux:text>
            </div>
            @if ($this->releases->isEmpty())
                <flux:text>{{ __('No releases recorded.') }}</flux:text>
            @else
                <flux:table class="[&_td]:align-top">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Version') }}</flux:table.column>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Released') }}</flux:table.column>
                        <flux:table.column>{{ __('Notes') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->releases as $release)
                            <flux:table.row>
                                <flux:table.cell class="font-medium tabular-nums">{{ $release->version }}</flux:table.cell>
                                <flux:table.cell>{{ $release->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$this->releaseStatusVariant($release->status)" size="sm">{{ $release->status }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>{{ $release->released_at?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ \Illuminate\Support\Str::limit($release->notes ?? '—', 100) }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Deployments') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $this->deployments->count() }} {{ __('total') }}</flux:text>
            </div>
            @if ($this->deployments->isEmpty())
                <flux:text>{{ __('No deployments recorded.') }}</flux:text>
            @else
                <flux:table class="[&_td]:align-top">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Environment') }}</flux:table.column>
                        <flux:table.column>{{ __('Release') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Deployed') }}</flux:table.column>
                        <flux:table.column>{{ __('URL') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->deployments as $deployment)
                            <flux:table.row>
                                <flux:table.cell class="font-medium">{{ $deployment->environment }}</flux:table.cell>
                                <flux:table.cell>{{ $deployment->release?->version ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$this->deploymentStatusVariant($deployment->status)" size="sm">{{ str_replace('_', ' ', $deployment->status) }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>{{ $deployment->deployed_at?->format('Y-m-d H:i') ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($deployment->url)
                                        <a href="{{ $deployment->url }}" target="_blank" rel="noopener" class="text-sky-600 underline dark:text-sky-400">{{ \Illuminate\Support\Str::limit($deployment->url, 40) }}</a>
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
                <flux:heading size="lg">{{ __('Delivery links') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $this->deliveryLinks->count() }} {{ __('linked') }}</flux:text>
            </div>
            @if ($this->deliveryLinks->isEmpty())
                <flux:text>{{ __('No delivery links recorded.') }}</flux:text>
            @else
                <flux:table class="[&_td]:align-top">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Work item') }}</flux:table.column>
                        <flux:table.column>{{ __('Type') }}</flux:table.column>
                        <flux:table.column>{{ __('Ref') }}</flux:table.column>
                        <flux:table.column>{{ __('Checks') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->deliveryLinks as $link)
                            <flux:table.row>
                                <flux:table.cell class="font-medium">{{ $link->workItem?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$this->deliveryTypeVariant($link->type)" size="sm">{{ str_replace('_', ' ', $link->type) }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($link->url)
                                        <a href="{{ $link->url }}" target="_blank" rel="noopener" class="font-mono text-xs text-sky-600 underline dark:text-sky-400">{{ $link->ref }}</a>
                                    @else
                                        <span class="font-mono text-xs">{{ $link->ref }}</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($link->checkRuns->isEmpty())
                                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No checks') }}</flux:text>
                                    @else
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($link->checkRuns as $check)
                                                <flux:badge :color="$this->checkConclusionVariant($check->conclusion)" size="sm">
                                                    {{ $check->name }}{{ $check->conclusion ? ': '.$check->conclusion : '' }}
                                                </flux:badge>
                                            @endforeach
                                        </div>
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
