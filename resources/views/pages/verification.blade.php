<?php

use App\Concerns\ProjectScoped;
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

    public function levelVariant(string $level): string
    {
        return match ($level) {
            'system' => 'purple',
            'integration' => 'indigo',
            'unit' => 'blue',
            default => 'zinc',
        };
    }

    public function anomalySeverityVariant(string $severity): string
    {
        return match ($severity) {
            'critical', 'high' => 'red',
            'medium' => 'amber',
            'low' => 'sky',
            default => 'zinc',
        };
    }

    public function anomalyStatusVariant(string $status): string
    {
        return match ($status) {
            'open' => 'red',
            'investigating' => 'amber',
            'resolved' => 'green',
            'closed' => 'zinc',
            default => 'zinc',
        };
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
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">{{ $plan->name }}</flux:heading>
                            <flux:badge :color="$this->levelVariant($plan->level)" size="sm">{{ $plan->level }}</flux:badge>
                        </div>
                        @if ($plan->scope)
                            <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $plan->scope }}</flux:text>
                        @endif
                    </div>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $plan->cases->count() }} {{ __('cases') }}</flux:text>
                </div>
                @if ($plan->cases->isEmpty())
                    <flux:text>{{ __('No test cases.') }}</flux:text>
                @else
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
                @endif
            </section>
        @endforeach

        @if ($this->testPlans->isEmpty())
            <flux:callout icon="information-circle">
                <flux:callout.heading>{{ __('No test plans yet') }}</flux:callout.heading>
                <flux:callout.text>{{ __('Create a test plan via the verification MCP server.') }}</flux:callout.text>
            </flux:callout>
        @endif

        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Anomalies') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $this->anomalies->where('status', '!=', 'closed')->count() }} {{ __('open') }} / {{ $this->anomalies->count() }} {{ __('total') }}
                </flux:text>
            </div>
            @if ($this->anomalies->isEmpty())
                <flux:text>{{ __('No anomalies reported.') }}</flux:text>
            @else
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
                                    <div class="font-medium">{{ $anomaly->summary }}</div>
                                    @if ($anomaly->description)
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ \Illuminate\Support\Str::limit($anomaly->description, 80) }}</div>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$this->anomalySeverityVariant($anomaly->severity)" size="sm">{{ $anomaly->severity }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$this->anomalyStatusVariant($anomaly->status)" size="sm">{{ $anomaly->status }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>{{ $anomaly->environment ?? '—' }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </section>
    @endif
</div>
