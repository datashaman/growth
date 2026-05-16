<?php

use App\Growth\Transitions\ApproveChangeRequest;
use App\Growth\Transitions\CancelChangeRequest;
use App\Growth\Transitions\DeferChangeRequest;
use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\MarkChangeRequestImplemented;
use App\Growth\Transitions\RejectChangeRequest;
use App\Growth\Transitions\SubmitChangeRequest;
use App\Growth\Transitions\Transition;
use App\Models\ChangeRequest;
use App\Support\BadgeVariant;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new class extends Component {
    public ChangeRequest $changeRequest;

    public function mount(ChangeRequest $changeRequest): void
    {
        $this->reload($changeRequest);
    }

    #[On('change-request-saved')]
    public function refresh(): void
    {
        $this->reload($this->changeRequest->fresh());
    }

    public function submitChangeRequest(): void
    {
        $this->applyTransition(new SubmitChangeRequest);
    }

    public function approveChangeRequest(): void
    {
        $this->applyTransition(new ApproveChangeRequest);
    }

    public function rejectChangeRequest(): void
    {
        $this->applyTransition(new RejectChangeRequest);
    }

    public function deferChangeRequest(): void
    {
        $this->applyTransition(new DeferChangeRequest);
    }

    public function markChangeRequestImplemented(): void
    {
        $this->applyTransition(new MarkChangeRequestImplemented);
    }

    public function cancelChangeRequest(): void
    {
        $this->applyTransition(new CancelChangeRequest);
    }

    private function applyTransition(Transition $transition): void
    {
        try {
            $transition->apply($this->changeRequest, auth()->user());
        } catch (IllegalTransitionException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->reload($this->changeRequest->fresh());

        Flux::toast(variant: 'success', text: __('Change request is now :status.', [
            'status' => str_replace('_', ' ', $this->changeRequest->status),
        ]));
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
            <flux:badge :color="BadgeVariant::changeRequestPriority($changeRequest->priority)" size="sm">{{ __('priority: :v', ['v' => $changeRequest->priority]) }}</flux:badge>
            <flux:badge :color="BadgeVariant::changeRequestStatus($changeRequest->status)" size="sm">{{ __('status: :v', ['v' => str_replace('_', ' ', $changeRequest->status)]) }}</flux:badge>
            @if ($changeRequest->decision)
                <flux:badge :color="BadgeVariant::changeRequestDecision($changeRequest->decision)" size="sm">{{ __('decision: :v', ['v' => $changeRequest->decision]) }}</flux:badge>
            @endif
        </x-slot:badges>

        <x-slot:description>
            {{ __('Change request in project') }} <a href="{{ route('dashboard', ['project' => $changeRequest->project_id]) }}" class="underline">{{ $changeRequest->project->name }}</a>
        </x-slot:description>

        <x-slot:actions>
            @if ($changeRequest->status === 'proposed')
                <flux:button size="sm" icon="paper-airplane" variant="primary" wire:click="submitChangeRequest">{{ __('Submit') }}</flux:button>
                <flux:button size="sm" icon="x-circle" variant="danger" wire:click="cancelChangeRequest">{{ __('Cancel') }}</flux:button>
            @elseif ($changeRequest->status === 'under_review')
                <flux:button size="sm" icon="check" variant="primary" wire:click="approveChangeRequest">{{ __('Approve') }}</flux:button>
                <flux:button size="sm" icon="x-mark" variant="danger" wire:click="rejectChangeRequest">{{ __('Reject') }}</flux:button>
                <flux:button size="sm" icon="clock" wire:click="deferChangeRequest">{{ __('Defer') }}</flux:button>
                <flux:button size="sm" icon="x-circle" variant="danger" wire:click="cancelChangeRequest">{{ __('Cancel') }}</flux:button>
            @elseif ($changeRequest->status === 'approved')
                <flux:button size="sm" icon="check-badge" variant="primary" wire:click="markChangeRequestImplemented">{{ __('Mark implemented') }}</flux:button>
            @elseif ($changeRequest->status === 'deferred')
                <flux:button size="sm" icon="x-circle" variant="danger" wire:click="cancelChangeRequest">{{ __('Cancel') }}</flux:button>
            @endif
            <flux:button
                size="sm"
                icon="pencil-square"
                variant="primary"
                wire:click="$dispatch('edit-change-request', { changeRequestId: '{{ $changeRequest->id }}' })">
                {{ __('Edit') }}
            </flux:button>
            <flux:modal.trigger name="delete-change-request">
                <flux:button size="sm" icon="trash" variant="danger">{{ __('Delete') }}</flux:button>
            </flux:modal.trigger>
        </x-slot:actions>
    </x-detail-page-header>

    <livewire:pages::change-requests.edit-modal :key="'edit-change-request-'.$changeRequest->id" />
    <livewire:pages::change-requests.delete-modal
        :change-request-id="$changeRequest->id"
        redirect-after="changes"
        :key="'delete-change-request-'.$changeRequest->id" />

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
                            <flux:table.cell class="font-mono text-xs">
                                {{ class_basename($impact->impactable_type) }}:{{ $impact->impactable_id }}
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
