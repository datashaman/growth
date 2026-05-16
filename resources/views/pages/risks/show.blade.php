<?php

use App\Growth\Transitions\AcceptRisk;
use App\Growth\Transitions\AssessRisk;
use App\Growth\Transitions\CloseRisk;
use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\MarkRiskMitigated;
use App\Growth\Transitions\MarkRiskRealized;
use App\Growth\Transitions\StartRiskMitigation;
use App\Growth\Transitions\Transition;
use App\Models\Risk;
use App\Support\BadgeVariant;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new class extends Component {
    public Risk $risk;

    public function mount(Risk $risk): void
    {
        $this->risk = $risk->load(['ownerRole', 'project']);
    }

    #[Title('Risk')]
    public function rendering(): void {}

    public function assessRisk(): void
    {
        $this->applyTransition(new AssessRisk);
    }

    public function startRiskMitigation(): void
    {
        $this->applyTransition(new StartRiskMitigation);
    }

    public function markRiskMitigated(): void
    {
        $this->applyTransition(new MarkRiskMitigated);
    }

    public function acceptRisk(): void
    {
        $this->applyTransition(new AcceptRisk);
    }

    public function markRiskRealized(): void
    {
        $this->applyTransition(new MarkRiskRealized);
    }

    public function closeRisk(): void
    {
        $this->applyTransition(new CloseRisk);
    }

    private function applyTransition(Transition $transition): void
    {
        try {
            $transition->apply($this->risk, auth()->user());
        } catch (IllegalTransitionException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->risk = $this->risk->fresh(['ownerRole', 'project']);

        Flux::toast(variant: 'success', text: __('Risk is now :status.', [
            'status' => str_replace('_', ' ', $this->risk->status),
        ]));
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-detail-page-header
        :title="$risk->title"
        back-route="dashboard"
        :back-label="__('Back to dashboard')">
        <x-slot:badges>
            <flux:badge :color="BadgeVariant::riskExposure($risk->probability, $risk->impact)" size="sm">
                {{ BadgeVariant::riskExposureLabel($risk->probability, $risk->impact) }}
            </flux:badge>
            <flux:badge :color="BadgeVariant::riskStatus($risk->status)" size="sm">
                {{ str_replace('_', ' ', $risk->status) }}
            </flux:badge>
            <flux:badge color="zinc" size="sm">{{ str_replace('_', ' ', $risk->category) }}</flux:badge>
        </x-slot:badges>

        <x-slot:description>
            {{ __('Risk in project') }} <a href="{{ route('dashboard', ['project' => $risk->project_id]) }}" class="underline">{{ $risk->project->name }}</a>
        </x-slot:description>

        <x-slot:actions>
            @if (in_array($risk->status, ['identified'], true))
                <flux:button size="sm" icon="magnifying-glass" variant="primary" wire:click="assessRisk">{{ __('Assess') }}</flux:button>
            @endif
            @if ($risk->status === 'assessed')
                <flux:button size="sm" icon="play" variant="primary" wire:click="startRiskMitigation">{{ __('Start mitigation') }}</flux:button>
            @endif
            @if ($risk->status === 'mitigating')
                <flux:button size="sm" icon="check" variant="primary" wire:click="markRiskMitigated">{{ __('Mark mitigated') }}</flux:button>
            @endif
            @if (in_array($risk->status, ['identified', 'assessed'], true))
                <flux:button size="sm" icon="hand-raised" wire:click="acceptRisk">{{ __('Accept') }}</flux:button>
            @endif
            @if (! in_array($risk->status, ['realized', 'closed'], true))
                <flux:button size="sm" icon="bolt" variant="danger" wire:click="markRiskRealized">{{ __('Mark realized') }}</flux:button>
            @endif
            @if (in_array($risk->status, ['mitigated', 'accepted', 'realized'], true))
                <flux:button size="sm" icon="archive-box" wire:click="closeRisk">{{ __('Close') }}</flux:button>
            @endif
            <flux:modal.trigger name="edit-risk">
                <flux:button size="sm" icon="pencil-square" variant="primary">{{ __('Edit') }}</flux:button>
            </flux:modal.trigger>
            <flux:modal.trigger name="delete-risk">
                <flux:button size="sm" icon="trash" variant="danger">{{ __('Delete') }}</flux:button>
            </flux:modal.trigger>
        </x-slot:actions>
    </x-detail-page-header>

    <livewire:pages::risks.edit-modal :risk-id="$risk->id" :key="'edit-risk-'.$risk->id" />
    <livewire:pages::risks.delete-modal :risk-id="$risk->id" :key="'delete-risk-'.$risk->id" />

    <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg" class="mb-3">{{ __('Properties') }}</flux:heading>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Probability') }}</dt>
                <dd class="mt-0.5">{{ $risk->probability ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Impact') }}</dt>
                <dd class="mt-0.5">{{ $risk->impact ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Owner role') }}</dt>
                <dd class="mt-0.5">{{ $risk->ownerRole?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Created') }}</dt>
                <dd class="mt-0.5">{{ $risk->created_at?->format('Y-m-d') ?? '—' }}</dd>
            </div>
        </dl>
    </section>

    @if ($risk->description)
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-3">{{ __('Description') }}</flux:heading>
            <flux:text class="whitespace-pre-line">{{ $risk->description }}</flux:text>
        </section>
    @endif

    @if ($risk->mitigation_plan)
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-3">{{ __('Mitigation plan') }}</flux:heading>
            <flux:text class="whitespace-pre-line">{{ $risk->mitigation_plan }}</flux:text>
        </section>
    @endif
</div>
