<?php

use App\Concerns\ProjectScoped;
use App\Models\UnattributedGithubEvent;
use App\Models\WorkItemDeliveryLink;
use App\Support\BadgeVariant;
use App\Support\EnumLabel;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Evidence')] class extends Component {
    use ProjectScoped;

    #[On('release-saved')]
    public function refreshReleases(): void
    {
        unset($this->releases);
    }

    #[On('deployment-saved')]
    public function refreshDeployments(): void
    {
        unset($this->deployments);
    }

    /**
     * @return array<string,string>
     */
    public function getListeners(): array
    {
        if ($this->selectedProjectId === null) {
            return [];
        }

        return [
            'echo-private:projects.'.$this->selectedProjectId.',ProjectDataChanged' => 'onProjectDataChanged',
        ];
    }

    public function onProjectDataChanged(): void
    {
        unset($this->releases, $this->deployments, $this->deliveryLinks, $this->unattributedEvents);
    }

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
            ->orderByDesc('id')
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
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * GitHub events that arrived but could not be matched to a work item.
     * Keyed by repository — an unattributed event has no work item, hence
     * no project, so it is resolved through the project's github_repo.
     */
    #[Computed]
    public function unattributedEvents()
    {
        if ($this->selectedProject?->github_repo === null) {
            return collect();
        }

        return UnattributedGithubEvent::query()
            ->where('github_repo', $this->selectedProject->github_repo)
            ->withinRetention()
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-project-page-header
        :title="__('Evidence')"
        :description="__('Releases, deployments, and the delivery artefacts that back them.')" />

    @if ($this->selectedProject === null)
        <flux:callout icon="cursor-arrow-rays">
            <flux:callout.heading>{{ __('Select a project') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Pick a project to see its evidence trail.') }}</flux:callout.text>
        </flux:callout>
    @else
        @if ($this->unattributedEvents->isNotEmpty())
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>
                    {{ trans_choice(
                        '{1} A GitHub event could not be matched to a work item|[2,*] :count GitHub events could not be matched to a work item',
                        $this->unattributedEvents->count(),
                        ['count' => $this->unattributedEvents->count()],
                    ) }}
                </flux:callout.heading>
                <flux:callout.text>
                    <div class="flex flex-col gap-3">
                        @foreach ($this->unattributedEvents as $event)
                            <div class="flex flex-col gap-0.5">
                                <div class="text-sm font-medium">
                                    {{ str_replace('_', ' ', $event->event_type) }}
                                    @if ($event->branch)
                                        · {{ __('branch') }} <span class="font-mono">{{ $event->branch }}</span>
                                    @endif
                                    · {{ $event->received_at->diffForHumans() }}
                                </div>
                                <div class="text-sm">
                                    @if ($event->reason === 'ambiguous_branch')
                                        {{ __('Branch :branch is bound to more than one work item, so the commit cannot be attributed.', ['branch' => $event->branch]) }}
                                    @else
                                        {{ __('The commit has no Growth-Work-Item trailer and its branch is not bound to a work item.') }}
                                    @endif
                                    {{ __('Bind the branch or add the trailer, then re-run the check.') }}
                                </div>
                                @if ($event->url)
                                    <a href="{{ $event->url }}" target="_blank" rel="noopener"
                                        class="font-mono text-xs text-sky-600 underline dark:text-sky-400">{{ \Illuminate\Support\Str::limit($event->commit_sha, 12, '') }}</a>
                                @else
                                    <span class="font-mono text-xs">{{ \Illuminate\Support\Str::limit($event->commit_sha, 12, '') }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </flux:callout.text>
            </flux:callout>
        @endif

        <x-data-table
            :title="__('Releases')"
            :count="$this->releases->count()"
            :count-label="__('total')"
            :empty="$this->releases->isEmpty()"
            :empty-message="__('No releases recorded.')">
            <x-slot:actions>
                <flux:modal.trigger name="create-release">
                    <flux:button size="sm" icon="plus" variant="primary">{{ __('New release') }}</flux:button>
                </flux:modal.trigger>
            </x-slot:actions>
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Version') }}</flux:table.column>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Released') }}</flux:table.column>
                    <flux:table.column>{{ __('Notes') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->releases as $release)
                        <flux:table.row>
                            <flux:table.cell class="font-medium tabular-nums">{{ $release->version }}</flux:table.cell>
                            <flux:table.cell>{{ $release->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::releaseStatus($release->status)" size="sm">{{ EnumLabel::lower($release->status) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $release->released_at?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ \Illuminate\Support\Str::limit($release->notes ?? '—', 100) }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end gap-1">
                                    <flux:button size="xs" icon="pencil-square" variant="ghost"
                                        wire:click="$dispatch('edit-release', { releaseId: '{{ $release->id }}' })" />
                                    <flux:button size="xs" icon="trash" variant="ghost"
                                        wire:click="$dispatch('delete-release', { releaseId: '{{ $release->id }}' })" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>

        <livewire:pages::releases.create-modal :project-id="$this->selectedProject->id" :key="'create-release-'.$this->selectedProject->id" />
        <livewire:pages::releases.edit-modal :key="'edit-release-'.$this->selectedProject->id" />
        <livewire:pages::releases.delete-modal :key="'delete-release-'.$this->selectedProject->id" />

        <x-data-table
            :title="__('Deployments')"
            :count="$this->deployments->count()"
            :count-label="__('total')"
            :empty="$this->deployments->isEmpty()"
            :empty-message="__('No deployments recorded.')">
            <x-slot:actions>
                <flux:modal.trigger name="create-deployment">
                    <flux:button size="sm" icon="plus" variant="primary">{{ __('New deployment') }}</flux:button>
                </flux:modal.trigger>
            </x-slot:actions>
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Environment') }}</flux:table.column>
                    <flux:table.column>{{ __('Release') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Deployed') }}</flux:table.column>
                    <flux:table.column>{{ __('URL') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->deployments as $deployment)
                        <flux:table.row>
                            <flux:table.cell class="font-medium">{{ $deployment->environment }}</flux:table.cell>
                            <flux:table.cell>{{ $deployment->release?->version ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::deploymentStatus($deployment->status)" size="sm">{{ str_replace('_', ' ', $deployment->status) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $deployment->deployed_at?->format('Y-m-d H:i') ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($deployment->url)
                                    <a href="{{ $deployment->url }}" target="_blank" rel="noopener" class="text-sky-600 underline dark:text-sky-400">{{ \Illuminate\Support\Str::limit($deployment->url, 40) }}</a>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end gap-1">
                                    <flux:button size="xs" icon="pencil-square" variant="ghost"
                                        wire:click="$dispatch('edit-deployment', { deploymentId: '{{ $deployment->id }}' })" />
                                    <flux:button size="xs" icon="trash" variant="ghost"
                                        wire:click="$dispatch('delete-deployment', { deploymentId: '{{ $deployment->id }}' })" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>

        <livewire:pages::deployments.create-modal :project-id="$this->selectedProject->id" :key="'create-deployment-'.$this->selectedProject->id" />
        <livewire:pages::deployments.edit-modal :key="'edit-deployment-'.$this->selectedProject->id" />
        <livewire:pages::deployments.delete-modal :key="'delete-deployment-'.$this->selectedProject->id" />

        <x-data-table
            :title="__('Delivery links')"
            :count="$this->deliveryLinks->count()"
            :count-label="__('linked')"
            :empty="$this->deliveryLinks->isEmpty()"
            :empty-message="__('No delivery links recorded.')">
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
                                <flux:badge :color="BadgeVariant::deliveryType($link->type)" size="sm">{{ str_replace('_', ' ', $link->type) }}</flux:badge>
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
                                            <flux:badge :color="BadgeVariant::checkConclusion($check->conclusion)" size="sm">
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
        </x-data-table>
    @endif
</div>
