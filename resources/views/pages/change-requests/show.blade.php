<?php

use App\Models\ChangeRequest;
use App\Support\ArtifactLink;
use App\Support\BadgeVariant;
use Livewire\Attributes\Title;
use Livewire\Component;

new class extends Component {
    public ChangeRequest $changeRequest;

    public function mount(ChangeRequest $changeRequest): void
    {
        $this->reload($changeRequest);
    }

    private function reload(ChangeRequest $cr): void
    {
        $this->changeRequest = $cr->load([
            'project',
            'requesterRole',
            'review',
            'impacts.impactable',
            'approvalEvents.recordedBy',
        ]);
    }

    #[Title('Change request')]
    public function rendering(): void {}
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-detail-page-header
        :title="$changeRequest->reference().' — '.$changeRequest->title"
        back-route="changes"
        :back-label="__('Back to changes')">
        <x-slot:badges>
            <flux:badge color="zinc" size="sm">{{ $changeRequest->category }}</flux:badge>
            <flux:badge :color="BadgeVariant::priority($changeRequest->priority)" size="sm">{{ __('priority: :v', ['v' => $changeRequest->priority]) }}</flux:badge>
            <flux:badge :color="BadgeVariant::changeRequestStatus($changeRequest->status)" size="sm">{{ __('status: :v', ['v' => str_replace('_', ' ', $changeRequest->status)]) }}</flux:badge>
            @if ($changeRequest->decision)
                <flux:badge :color="BadgeVariant::changeRequestDecision($changeRequest->decision)" size="sm">{{ __('decision: :v', ['v' => $changeRequest->decision]) }}</flux:badge>
            @endif
        </x-slot:badges>

        <x-slot:description>
            {{ __('Change request in project') }} <a href="{{ route('dashboard', ['project' => $changeRequest->project_id]) }}" class="underline">{{ $changeRequest->project->name }}</a>
        </x-slot:description>

    </x-detail-page-header>

    <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg" class="mb-3">{{ __('Properties') }}</flux:heading>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Requester role') }}</dt>
                <dd class="mt-0.5">{{ $changeRequest->requesterRole?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Linked review') }}</dt>
                <dd class="mt-0.5">
                    @if ($changeRequest->review)
                        <a href="{{ route('reviews.show', $changeRequest->review) }}" wire:navigate class="hover:underline">{{ $changeRequest->review->title }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Decided') }}</dt>
                <dd class="mt-0.5">{{ $changeRequest->decided_at?->format('Y-m-d') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Created') }}</dt>
                <dd class="mt-0.5">{{ $changeRequest->created_at?->format('Y-m-d') ?? '—' }}</dd>
            </div>
        </dl>
    </section>

    @if ($changeRequest->description)
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-3">{{ __('Description') }}</flux:heading>
            <flux:text class="whitespace-pre-line">{{ $changeRequest->description }}</flux:text>
        </section>
    @endif

    @if ($changeRequest->rationale)
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-3">{{ __('Rationale') }}</flux:heading>
            <flux:text class="whitespace-pre-line">{{ $changeRequest->rationale }}</flux:text>
        </section>
    @endif

    @if ($changeRequest->decision_rationale)
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-3">{{ __('Decision rationale') }}</flux:heading>
            <flux:text class="whitespace-pre-line">{{ $changeRequest->decision_rationale }}</flux:text>
        </section>
    @endif

    @if ($changeRequest->impacts->isNotEmpty())
        <x-data-table
            :title="__('Impacts')"
            :count="$changeRequest->impacts->count()">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Kind') }}</flux:table.column>
                    <flux:table.column>{{ __('Artifact') }}</flux:table.column>
                    <flux:table.column>{{ __('Description') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($changeRequest->impacts as $impact)
                        <flux:table.row>
                            <flux:table.cell>
                                <flux:badge color="zinc" size="sm">{{ str_replace('_', ' ', $impact->impact_kind) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                @php($artifact = $impact->impactable)
                                @if ($artifact && ($href = ArtifactLink::route($artifact)))
                                    <a href="{{ $href }}" wire:navigate class="font-medium hover:underline">{{ ArtifactLink::label($artifact) }}</a>
                                @elseif ($artifact)
                                    <span class="font-medium">{{ ArtifactLink::label($artifact) }}</span>
                                @else
                                    <span class="text-zinc-500 dark:text-zinc-400">{{ __('deleted') }}</span>
                                @endif
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ ArtifactLink::typeLabel($impact->impactable_type) }}</div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $impact->description ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif

    @if ($changeRequest->approvalEvents->isNotEmpty())
        <x-data-table
            :title="__('Approval events')"
            :count="$changeRequest->approvalEvents->count()">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Recorded') }}</flux:table.column>
                    <flux:table.column>{{ __('By') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Decision') }}</flux:table.column>
                    <flux:table.column>{{ __('Rationale') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($changeRequest->approvalEvents->sortByDesc('recorded_at') as $event)
                        <flux:table.row>
                            <flux:table.cell>{{ $event->recorded_at?->format('Y-m-d H:i') ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $event->recordedBy?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($event->from_status || $event->to_status)
                                    <span class="text-xs">{{ str_replace('_', ' ', $event->from_status ?? '—') }} → {{ str_replace('_', ' ', $event->to_status ?? '—') }}</span>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($event->from_decision || $event->to_decision)
                                    <span class="text-xs">{{ $event->from_decision ?? '—' }} → {{ $event->to_decision ?? '—' }}</span>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $event->rationale ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif
</div>
