<?php

use App\Concerns\ProjectScoped;
use App\Models\UnattributedGithubEvent;
use App\Models\WorkItemDeliveryLink;
use App\Support\BadgeVariant;
use App\Support\EnumLabel;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Evidence')] class extends Component {
    use ProjectScoped;

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

    /**
     * Unmatched events grouped by the reason they could not be attributed, so
     * the explanatory message is stated once per reason rather than repeated
     * verbatim for every event. Each group lists its own events as subjects.
     *
     * @return Collection<int,array{reason:string,message:string,events:Collection<int,UnattributedGithubEvent>}>
     */
    #[Computed]
    public function unattributedEventGroups(): Collection
    {
        return $this->unattributedEvents
            ->groupBy(fn (UnattributedGithubEvent $event): string => $event->reason === 'ambiguous_branch' ? 'ambiguous_branch' : 'unbound')
            ->map(fn (Collection $events, string $reason): array => [
                'reason' => $reason,
                'message' => $this->unattributedReasonMessage($reason),
                'events' => $events,
            ])
            ->values();
    }

    private function unattributedReasonMessage(string $reason): string
    {
        return $reason === 'ambiguous_branch'
            ? __('The branch is bound to more than one work item, so the commit cannot be attributed. Bind the branch to a single work item, then re-run the check.')
            : __('The commit has no Growth-Work-Item trailer and its branch is not bound to a work item. Bind the branch or add the trailer, then re-run the check.');
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
                    <details class="group">
                        <summary class="cursor-pointer text-sm underline">
                            <span class="group-open:hidden">{{ __('Show details') }}</span>
                            <span class="hidden group-open:inline">{{ __('Hide details') }}</span>
                        </summary>
                        <div class="mt-3 flex flex-col gap-4">
                            @foreach ($this->unattributedEventGroups as $group)
                                <div class="flex flex-col gap-1">
                                    <div class="text-sm">{{ $group['message'] }}</div>
                                    <ul class="flex flex-col gap-1">
                                        @foreach ($group['events'] as $event)
                                            <li class="text-xs">
                                                {{ str_replace('_', ' ', $event->event_type) }}
                                                @if ($event->branch)
                                                    · {{ __('branch') }} <span class="font-mono">{{ $event->branch }}</span>
                                                @endif
                                                · {{ $event->received_at->diffForHumans() }}
                                                ·
                                                @if ($event->url)
                                                    <a href="{{ $event->url }}" target="_blank" rel="noopener"
                                                        class="font-mono text-sky-600 underline dark:text-sky-400">{{ \Illuminate\Support\Str::limit($event->commit_sha, 12, '') }}</a>
                                                @else
                                                    <span class="font-mono">{{ \Illuminate\Support\Str::limit($event->commit_sha, 12, '') }}</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </details>
                </flux:callout.text>
            </flux:callout>
        @endif

        <x-data-table
            :title="__('Releases')"
            :count="$this->releases->count()"
            :count-label="__('total')"
            :empty="$this->releases->isEmpty()"
            :empty-message="__('No releases recorded.')">
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
                                <flux:badge :color="BadgeVariant::releaseStatus($release->status)" size="sm">{{ EnumLabel::lower($release->status) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $release->released_at?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ \Illuminate\Support\Str::limit($release->notes ?? '—', 100) }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>

        <x-data-table
            :title="__('Deployments')"
            :count="$this->deployments->count()"
            :count-label="__('total')"
            :empty="$this->deployments->isEmpty()"
            :empty-message="__('No deployments recorded.')">
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
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>

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
