<?php

use App\Concerns\ProjectScoped;
use App\Models\Requirement;
use App\Support\BadgeVariant;
use App\Support\EnumLabel;
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

    public function clearFilters(): void
    {
        $this->typeFilter = 'all';
        $this->priorityFilter = 'all';

        unset($this->requirements);
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
        <x-data-table
            :count="$this->requirements->count()"
            :count-label="__('captured')"
            :empty="$this->requirements->isEmpty()"
            :empty-message="$this->isFiltered() ? __('No requirements match the current filter.') : __('No requirements captured.')">
            <x-slot:header>
                <div class="flex w-full flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-baseline gap-3">
                        <flux:heading size="lg">{{ __('Requirements') }}</flux:heading>
                        <flux:text class="whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $this->requirements->count() }} {{ __('captured') }}
                        </flux:text>
                    </div>
                    <div class="grid w-full grid-cols-1 gap-2 sm:w-auto sm:grid-cols-2 lg:justify-end">
                        <flux:select wire:model.live="typeFilter" size="sm" class="w-full sm:w-48" data-test="requirements-type-filter">
                            <flux:select.option value="all">{{ __('All types') }}</flux:select.option>
                            @foreach (self::TYPES as $type)
                                <flux:select.option value="{{ $type }}">{{ EnumLabel::lower($type) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model.live="priorityFilter" size="sm" class="w-full sm:w-48" data-test="requirements-priority-filter">
                            <flux:select.option value="all">{{ __('All priorities') }}</flux:select.option>
                            @foreach (self::PRIORITIES as $priority)
                                <flux:select.option value="{{ $priority }}">{{ EnumLabel::lower($priority) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @if ($this->isFiltered())
                            <flux:button wire:click="clearFilters" size="sm" variant="subtle" class="sm:col-span-2 lg:col-span-1" data-test="requirements-clear-filters">
                                {{ __('Clear filters') }}
                            </flux:button>
                        @endif
                    </div>
                </div>
            </x-slot:header>
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column class="w-28">{{ __('ID') }}</flux:table.column>
                    <flux:table.column>{{ __('Statement') }}</flux:table.column>
                    <flux:table.column class="w-40">{{ __('Type') }}</flux:table.column>
                    <flux:table.column class="w-28">{{ __('Priority') }}</flux:table.column>
                    <flux:table.column class="w-32">{{ __('Verification') }}</flux:table.column>
                    <flux:table.column>{{ __('Source') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->requirements as $requirement)
                        @php($state = $this->verificationState($requirement))
                        <flux:table.row>
                            <flux:table.cell class="whitespace-nowrap">
                                <a href="{{ route('requirements.show', $requirement) }}" wire:navigate class="font-mono text-xs font-medium text-zinc-700 hover:underline dark:text-zinc-300" aria-label="{{ __('Open requirement :reference', ['reference' => $requirement->reference()]) }}">
                                    {{ $requirement->reference() }}
                                </a>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="max-w-3xl">
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ \Illuminate\Support\Str::limit($requirement->text, 180) }}</div>
                                    @if ($requirement->rationale)
                                        <div class="mt-1 text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                                            <span class="font-medium text-zinc-600 dark:text-zinc-300">{{ __('Rationale:') }}</span>
                                            {{ \Illuminate\Support\Str::limit($requirement->rationale, 180) }}
                                        </div>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">
                                <flux:badge color="zinc" size="sm">{{ EnumLabel::lower($requirement->type) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">
                                <flux:badge :color="BadgeVariant::priority($requirement->priority)" size="sm">{{ EnumLabel::lower($requirement->priority) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">
                                <flux:badge :color="BadgeVariant::requirementVerification($state)" size="sm">{{ EnumLabel::lower($state) }}</flux:badge>
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
