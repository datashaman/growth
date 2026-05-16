<?php

use App\Growth\Transitions\CloseAnomaly;
use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\ReopenAnomaly;
use App\Growth\Transitions\ResolveAnomaly;
use App\Growth\Transitions\StartAnomalyInvestigation;
use App\Growth\Transitions\Transition;
use App\Models\Anomaly;
use App\Support\BadgeVariant;
use App\Support\EnumLabel;
use Flux\Flux;
use Livewire\Component;

new class extends Component {
    public Anomaly $anomaly;

    public function mount(Anomaly $anomaly): void
    {
        $this->anomaly = $anomaly->load($this->relations());
    }

    /**
     * Relations eager-loaded for the detail view.
     *
     * @return list<string>
     */
    private function relations(): array
    {
        return ['project', 'testRun', 'affectedRequirements'];
    }

    public function startAnomalyInvestigation(): void
    {
        $this->applyTransition(new StartAnomalyInvestigation);
    }

    public function resolveAnomaly(): void
    {
        $this->applyTransition(new ResolveAnomaly);
    }

    public function closeAnomaly(): void
    {
        $this->applyTransition(new CloseAnomaly);
    }

    public function reopenAnomaly(): void
    {
        $this->applyTransition(new ReopenAnomaly);
    }

    private function applyTransition(Transition $transition): void
    {
        try {
            $transition->apply($this->anomaly, auth()->user());
        } catch (IllegalTransitionException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->anomaly = $this->anomaly->fresh($this->relations());

        Flux::toast(variant: 'success', text: __('Anomaly is now :status.', [
            'status' => str_replace('_', ' ', $this->anomaly->status),
        ]));
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-detail-page-header
        :title="$anomaly->summary"
        back-route="verification"
        :back-label="__('Back to verification')">
        <x-slot:badges>
            <flux:badge :color="BadgeVariant::anomalySeverity($anomaly->severity)" size="sm">{{ EnumLabel::lower($anomaly->severity) }}</flux:badge>
            <flux:badge :color="BadgeVariant::anomalyStatus($anomaly->status)" size="sm">{{ EnumLabel::lower($anomaly->status) }}</flux:badge>
        </x-slot:badges>
        <x-slot:description>
            {{ __('Anomaly in project') }} <a href="{{ route('dashboard', ['project' => $anomaly->project_id]) }}" class="underline">{{ $anomaly->project->name }}</a>
        </x-slot:description>

        <x-slot:actions>
            @if ($anomaly->status === 'open')
                <flux:button size="sm" icon="magnifying-glass" variant="primary" wire:click="startAnomalyInvestigation">{{ __('Start investigation') }}</flux:button>
            @endif
            @if ($anomaly->status === 'investigating')
                <flux:button size="sm" icon="check" variant="primary" wire:click="resolveAnomaly">{{ __('Resolve') }}</flux:button>
            @endif
            @if ($anomaly->status === 'resolved')
                <flux:button size="sm" icon="archive-box" wire:click="closeAnomaly">{{ __('Close') }}</flux:button>
            @endif
            @if (in_array($anomaly->status, ['resolved', 'closed'], true))
                <flux:button size="sm" icon="arrow-path" wire:click="reopenAnomaly">{{ __('Reopen') }}</flux:button>
            @endif
            <flux:modal.trigger name="edit-anomaly">
                <flux:button size="sm" icon="pencil-square" variant="primary">{{ __('Edit') }}</flux:button>
            </flux:modal.trigger>
            <flux:modal.trigger name="delete-anomaly">
                <flux:button size="sm" icon="trash" variant="danger">{{ __('Delete') }}</flux:button>
            </flux:modal.trigger>
        </x-slot:actions>
    </x-detail-page-header>

    <livewire:pages::anomalies.edit-modal :anomaly-id="$anomaly->id" :key="'edit-anomaly-'.$anomaly->id" />
    <livewire:pages::anomalies.delete-modal :anomaly-id="$anomaly->id" :key="'delete-anomaly-'.$anomaly->id" />

    <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg" class="mb-3">{{ __('Properties') }}</flux:heading>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Environment') }}</dt>
                <dd class="mt-0.5">{{ $anomaly->environment ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Reported') }}</dt>
                <dd class="mt-0.5">{{ $anomaly->created_at?->format('Y-m-d') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Test run') }}</dt>
                <dd class="mt-0.5">{{ $anomaly->testRun?->id ?? '—' }}</dd>
            </div>
        </dl>
    </section>

    @if ($anomaly->description)
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-3">{{ __('Description') }}</flux:heading>
            <flux:text class="whitespace-pre-line">{{ $anomaly->description }}</flux:text>
        </section>
    @endif

    @if ($anomaly->affectedRequirements->isNotEmpty())
        <x-data-table
            :title="__('Affected requirements')"
            :count="$anomaly->affectedRequirements->count()">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Requirement') }}</flux:table.column>
                    <flux:table.column>{{ __('Doc') }}</flux:table.column>
                    <flux:table.column>{{ __('Priority') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($anomaly->affectedRequirements as $req)
                        <flux:table.row>
                            <flux:table.cell>
                                <a href="{{ route('requirements.show', $req) }}" wire:navigate class="hover:underline">{{ \Illuminate\Support\Str::limit($req->text, 80) }}</a>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::doc($req->doc)" size="sm">{{ strtoupper($req->doc) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::priority($req->priority)" size="sm">{{ $req->priority ?? '—' }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif
</div>
