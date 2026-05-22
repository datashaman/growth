<?php

use App\Concerns\ProjectScoped;
use App\Models\Requirement;
use App\Support\BadgeVariant;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Requirements')] class extends Component {
    use ProjectScoped;

    /**
     * Filter on requirement type; 'all' shows every type.
     */
    public string $typeFilter = 'all';

    /**
     * Filter on requirement priority; 'all' shows every priority.
     */
    public string $priorityFilter = 'all';

    /**
     * @var list<string>
     */
    public const TYPES = [
        'functional', 'performance', 'usability', 'interface',
        'design_constraint', 'process', 'non_functional',
    ];

    /**
     * @var list<string>
     */
    public const PRIORITIES = ['high', 'medium', 'low'];

    /**
     * @return array<string,string>
     */
    public function getListeners(): array
    {
        return $this->projectScopedListeners();
    }

    public function onProjectDataChanged(): void
    {
        unset($this->requirements);
    }

    public function updatedTypeFilter(): void
    {
        unset($this->requirements);
    }

    public function updatedPriorityFilter(): void
    {
        unset($this->requirements);
    }

    #[Computed]
    public function requirements()
    {
        return $this->selectedProject?->requirements()
            ->when($this->typeFilter !== 'all', fn ($query) => $query->where('type', $this->typeFilter))
            ->when($this->priorityFilter !== 'all', fn ($query) => $query->where('priority', $this->priorityFilter))
            ->with('testCases.latestRun')
            ->orderBy('doc')
            ->orderBy('created_at')
            ->get()
            ?? collect();
    }

    /**
     * Verification coverage for a requirement: verified when a linked test case
     * has a passing latest run, covered when cases exist but none pass, otherwise
     * uncovered.
     */
    public function verificationState(Requirement $requirement): string
    {
        if ($requirement->testCases->isEmpty()) {
            return 'uncovered';
        }

        $verified = $requirement->testCases
            ->contains(fn ($case) => $case->latestRun?->status === 'pass');

        return $verified ? 'verified' : 'covered';
    }

    public function isFiltered(): bool
    {
        return $this->typeFilter !== 'all' || $this->priorityFilter !== 'all';
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-project-page-header
        :title="__('Requirements')"
        :description="__('Requirements the system must satisfy.')" />

    @if ($this->selectedProject === null)
        <flux:callout icon="cursor-arrow-rays">
            <flux:callout.heading>{{ __('Select a project') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Pick a project from the dropdown to see its requirements.') }}</flux:callout.text>
        </flux:callout>
    @else
        <div class="flex flex-wrap justify-end gap-2">
            <flux:select wire:model.live="typeFilter" size="sm" class="max-w-3xs" data-test="requirements-type-filter">
                <flux:select.option value="all">{{ __('All types') }}</flux:select.option>
                @foreach (self::TYPES as $type)
                    <flux:select.option value="{{ $type }}">{{ str_replace('_', ' ', $type) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="priorityFilter" size="sm" class="max-w-3xs" data-test="requirements-priority-filter">
                <flux:select.option value="all">{{ __('All priorities') }}</flux:select.option>
                @foreach (self::PRIORITIES as $priority)
                    <flux:select.option value="{{ $priority }}">{{ $priority }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <x-data-table
            :count="$this->requirements->count()"
            :count-label="__('captured')"
            :empty="$this->requirements->isEmpty()"
            :empty-message="$this->isFiltered() ? __('No requirements match the current filter.') : __('No requirements captured.')">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('ID') }}</flux:table.column>
                    <flux:table.column>{{ __('Statement') }}</flux:table.column>
                    <flux:table.column>{{ __('Doc') }}</flux:table.column>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                    <flux:table.column>{{ __('Priority') }}</flux:table.column>
                    <flux:table.column>{{ __('Verification') }}</flux:table.column>
                    <flux:table.column>{{ __('Source') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->requirements as $requirement)
                        @php($state = $this->verificationState($requirement))
                        <flux:table.row>
                            <flux:table.cell>
                                <span class="font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $requirement->slug }}</span>
                            </flux:table.cell>
                            <flux:table.cell>
                                <a href="{{ route('requirements.show', $requirement) }}" wire:navigate class="hover:underline">{{ $requirement->text }}</a>
                                @if ($requirement->rationale)
                                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ \Illuminate\Support\Str::limit($requirement->rationale, 120) }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::doc($requirement->doc)" size="sm">{{ strtoupper($requirement->doc) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ str_replace('_', ' ', $requirement->type) }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::priority($requirement->priority)" size="sm">{{ $requirement->priority ?? '—' }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::requirementVerification($state)" size="sm">{{ $state }}</flux:badge>
                                @if ($requirement->testCases->isNotEmpty())
                                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ trans_choice(':count case|:count cases', $requirement->testCases->count()) }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $requirement->source ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif
</div>
