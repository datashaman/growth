<?php

use App\Concerns\ProjectScoped;
use App\Support\BadgeVariant;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Verification')] class extends Component {
    use ProjectScoped;

    #[Computed]
    public function testPlans()
    {
        return $this->selectedProject?->testPlans()
            ->with('cases')
            ->orderBy('level')
            ->orderBy('name')
            ->get()
            ?? collect();
    }

    #[Computed]
    public function anomalies()
    {
        return $this->selectedProject?->anomalies()
            ->orderBy('created_at', 'desc')
            ->get()
            ?? collect();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-project-page-header
        :title="__('Verification')"
        :description="__('Test plans, test cases, and the anomalies they surface.')"
        :options="$this->projectOptions" />

    @if ($this->selectedProject === null)
        <flux:callout icon="cursor-arrow-rays">
            <flux:callout.heading>{{ __('Select a project') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Pick a project to see its verification artefacts.') }}</flux:callout.text>
        </flux:callout>
    @else
        @foreach ($this->testPlans as $plan)
            <x-data-table
                :empty="$plan->cases->isEmpty()"
                :empty-message="__('No test cases.')">
                <x-slot:header>
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">{{ $plan->name }}</flux:heading>
                            <flux:badge :color="BadgeVariant::testLevel($plan->level)" size="sm">{{ $plan->level }}</flux:badge>
                        </div>
                        @if ($plan->scope)
                            <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $plan->scope }}</flux:text>
                        @endif
                    </div>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $plan->cases->count() }} {{ __('cases') }}</flux:text>
                </x-slot:header>

                <flux:table class="[&_td]:align-top">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Case') }}</flux:table.column>
                        <flux:table.column>{{ __('Objective') }}</flux:table.column>
                        <flux:table.column>{{ __('Environment') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($plan->cases as $case)
                            <flux:table.row>
                                <flux:table.cell class="font-medium">{{ $case->name }}</flux:table.cell>
                                <flux:table.cell>{{ $case->objective ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $case->environment ?? '—' }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </x-data-table>
        @endforeach

        @if ($this->testPlans->isEmpty())
            <flux:callout icon="information-circle">
                <flux:callout.heading>{{ __('No test plans yet') }}</flux:callout.heading>
                <flux:callout.text>{{ __('Create a test plan via the verification MCP server.') }}</flux:callout.text>
            </flux:callout>
        @endif

        <x-data-table
            :title="__('Anomalies')"
            :count="$this->anomalies->where('status', '!=', 'closed')->count().' '.__('open').' / '.$this->anomalies->count()"
            :count-label="__('total')"
            :empty="$this->anomalies->isEmpty()"
            :empty-message="__('No anomalies reported.')">
            <x-slot:actions>
                <flux:modal.trigger name="create-anomaly">
                    <flux:button size="sm" icon="plus" variant="primary">{{ __('Report anomaly') }}</flux:button>
                </flux:modal.trigger>
            </x-slot:actions>
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Anomaly') }}</flux:table.column>
                    <flux:table.column>{{ __('Severity') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Environment') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->anomalies as $anomaly)
                        <flux:table.row>
                            <flux:table.cell>
                                <a href="{{ route('anomalies.show', $anomaly) }}" wire:navigate class="font-medium hover:underline">{{ $anomaly->summary }}</a>
                                @if ($anomaly->description)
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ \Illuminate\Support\Str::limit($anomaly->description, 80) }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::anomalySeverity($anomaly->severity)" size="sm">{{ $anomaly->severity }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::anomalyStatus($anomaly->status)" size="sm">{{ $anomaly->status }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $anomaly->environment ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>

        <livewire:pages::anomalies.create-modal :project-id="$this->selectedProject->id" :key="'create-anomaly-'.$this->selectedProject->id" />
    @endif
</div>
