<?php

use App\Models\ChangeRequest;
use App\Support\ArtifactLink;
use App\Support\BadgeVariant;
use App\Support\EnumLabel;
use Illuminate\Support\Str;
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

    public function markdown(?string $markdown): string
    {
        return Str::markdown($markdown ?? '', [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-detail-page-header
        :title="$changeRequest->reference().' — '.$changeRequest->title"
        back-route="changes"
        :back-label="__('Back to changes')">
        <x-slot:badges>
            <flux:badge color="zinc" size="sm">{{ $changeRequest->category }}</flux:badge>
            <flux:badge :color="BadgeVariant::priority($changeRequest->priority)" size="sm">{{ __('priority: :v', ['v' => EnumLabel::lower($changeRequest->priority)]) }}</flux:badge>
            <flux:badge :color="BadgeVariant::changeRequestStatus($changeRequest->status)" size="sm">{{ __('status: :v', ['v' => EnumLabel::lower($changeRequest->status)]) }}</flux:badge>
            @if ($changeRequest->decision)
                <flux:badge :color="BadgeVariant::changeRequestDecision($changeRequest->decision)" size="sm">{{ __('decision: :v', ['v' => EnumLabel::lower($changeRequest->decision)]) }}</flux:badge>
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
            <div class="prose prose-sm max-w-none dark:prose-invert">
                {!! $this->markdown($changeRequest->description) !!}
            </div>
        </section>
    @endif

    @if ($changeRequest->rationale)
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-3">{{ __('Rationale') }}</flux:heading>
            <div class="prose prose-sm max-w-none dark:prose-invert">
                {!! $this->markdown($changeRequest->rationale) !!}
            </div>
        </section>
    @endif

    @if ($changeRequest->decision_rationale)
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-3">{{ __('Decision rationale') }}</flux:heading>
            <div class="prose prose-sm max-w-none dark:prose-invert">
                {!! $this->markdown($changeRequest->decision_rationale) !!}
            </div>
        </section>
    @endif

    @if ($changeRequest->impacts->isNotEmpty())
        <x-data-table
            :title="__('Impacts')"
            :count="$changeRequest->impacts->count()">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column class="w-36">{{ __('Kind') }}</flux:table.column>
                    <flux:table.column class="w-1/2">{{ __('Artifact') }}</flux:table.column>
                    <flux:table.column>{{ __('Description') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($changeRequest->impacts as $impact)
                        <flux:table.row>
                            <flux:table.cell class="whitespace-nowrap">
                                <flux:badge color="zinc" size="sm">{{ str_replace('_', ' ', $impact->impact_kind) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="whitespace-normal break-words">
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
                            <flux:table.cell class="whitespace-normal break-words">{{ $impact->description ?? '—' }}</flux:table.cell>
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
                            <flux:table.cell class="whitespace-nowrap">{{ $event->recorded_at?->format('Y-m-d H:i') ?? '—' }}</flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">{{ $event->recordedBy?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">
                                @if ($event->from_status || $event->to_status)
                                    <span class="inline-flex items-center gap-1">
                                        <flux:badge :color="BadgeVariant::changeRequestStatus($event->from_status ?? '')" size="sm">{{ EnumLabel::lower($event->from_status) }}</flux:badge>
                                        <span class="text-xs text-zinc-400 dark:text-zinc-500">-></span>
                                        <flux:badge :color="BadgeVariant::changeRequestStatus($event->to_status ?? '')" size="sm">{{ EnumLabel::lower($event->to_status) }}</flux:badge>
                                    </span>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">
                                @if ($event->from_decision || $event->to_decision)
                                    <span class="inline-flex items-center gap-1">
                                        <flux:badge :color="BadgeVariant::changeRequestDecision($event->from_decision)" size="sm">{{ EnumLabel::lower($event->from_decision) }}</flux:badge>
                                        <span class="text-xs text-zinc-400 dark:text-zinc-500">-></span>
                                        <flux:badge :color="BadgeVariant::changeRequestDecision($event->to_decision)" size="sm">{{ EnumLabel::lower($event->to_decision) }}</flux:badge>
                                    </span>
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
