<?php

use App\Concerns\ProjectScoped;
use App\Support\BadgeVariant;
use App\Support\EnumLabel;
use App\Support\TableColumn;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Intent')] class extends Component {
    use ProjectScoped;

    #[Computed]
    public function stakeholders()
    {
        return $this->selectedProject?->stakeholders()->orderBy('name')->get() ?? collect();
    }

    #[Computed]
    public function concerns()
    {
        return $this->selectedProject?->concerns()
            ->with(['raisedBy', 'designViews'])
            ->orderBy('created_at', 'desc')
            ->get() ?? collect();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-project-page-header
        :title="__('Intent')"
        :description="__('Stakeholders raising concerns the project must address.')" />

    @if ($this->selectedProject === null)
        <flux:callout icon="cursor-arrow-rays">
            <flux:callout.heading>{{ __('Select a project') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Pick a project from the dropdown to see its intent artefacts.') }}</flux:callout.text>
        </flux:callout>
    @else
        <x-data-table
            :title="__('Stakeholders')"
            :count="$this->stakeholders->count()"
            :count-label="__('listed')"
            :empty="$this->stakeholders->isEmpty()"
            :empty-message="__('No stakeholders recorded.')">
            <flux:table class="w-full table-fixed [&_td]:align-top [&_td]:break-words">
                <flux:table.columns>
                    <flux:table.column class="w-40">{{ __('Name') }}</flux:table.column>
                    <flux:table.column class="w-32">{{ __('Role') }}</flux:table.column>
                    <flux:table.column class="w-24">{{ __('Kind') }}</flux:table.column>
                    <flux:table.column>{{ __('Description') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->stakeholders as $stakeholder)
                        <flux:table.row>
                            <flux:table.cell class="font-medium whitespace-normal">{{ $stakeholder->name }}</flux:table.cell>
                            <flux:table.cell class="whitespace-normal">{{ $stakeholder->role ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::stakeholderKind($stakeholder->kind)" size="sm">{{ EnumLabel::lower($stakeholder->kind) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="whitespace-normal">{{ $stakeholder->description ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>

        <x-data-table
            :title="__('Concerns')"
            :count="$this->concerns->count()"
            :count-label="__('raised')"
            :empty="$this->concerns->isEmpty()"
            :empty-message="__('No concerns raised.')">
            @php($showHints = TableColumn::hasValues($this->concerns, fn ($concern) => $concern->viewpoint_hints))
            <flux:table class="w-full table-fixed [&_td]:align-top [&_td]:break-words">
                <flux:table.columns>
                    <flux:table.column>{{ __('Concern') }}</flux:table.column>
                    <flux:table.column class="w-40">{{ __('Raised by') }}</flux:table.column>
                    <flux:table.column class="w-48">{{ __('Addressed by') }}</flux:table.column>
                    @if ($showHints)
                        <flux:table.column class="w-48">{{ __('Viewpoint hints') }}</flux:table.column>
                    @endif
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->concerns as $concern)
                        <flux:table.row>
                            <flux:table.cell class="whitespace-normal">{{ $concern->text }}</flux:table.cell>
                            <flux:table.cell class="whitespace-normal">{{ $concern->raisedBy?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell class="whitespace-normal">
                                @php($addressingViews = $concern->designViews->sortBy([['viewpoint', 'asc'], ['name', 'asc']])->values())
                                @if ($addressingViews->isNotEmpty())
                                    <div class="flex flex-col gap-1">
                                        <a href="{{ route('architecture') }}" wire:navigate class="text-sm font-medium hover:underline">
                                            {{ trans_choice('{1} 1 architecture view|[2,*] :count architecture views', $addressingViews->count(), ['count' => $addressingViews->count()]) }}
                                        </a>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $addressingViews->pluck('name')->join(', ') }}
                                        </div>
                                    </div>
                                @else
                                    <span class="text-zinc-500 dark:text-zinc-400">{{ __('Not yet addressed') }}</span>
                                @endif
                            </flux:table.cell>
                            @if ($showHints)
                                <flux:table.cell class="whitespace-normal">
                                    @if ($concern->viewpoint_hints)
                                        {{ is_array($concern->viewpoint_hints) ? implode(', ', $concern->viewpoint_hints) : $concern->viewpoint_hints }}
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                            @endif
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif
</div>
