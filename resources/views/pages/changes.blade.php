<?php

use App\Concerns\ProjectScoped;
use App\Support\BadgeVariant;
use App\Support\EnumLabel;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Changes')] class extends Component {
    use ProjectScoped;

    /**
     * @return array<string,string>
     */
    public function getListeners(): array
    {
        return $this->projectScopedListeners();
    }

    public function onProjectDataChanged(): void
    {
        unset($this->changeRequests);
    }

    #[Computed]
    public function changeRequests()
    {
        return $this->selectedProject?->changeRequests()
            ->with(['requesterRole', 'review'])
            ->orderByDesc('number')
            ->get()
            ?? collect();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-project-page-header
        :title="__('Changes')"
        :description="__('Proposed changes to scope, requirements, design, plan, or risk — and their decisions.')" />


    @if ($this->selectedProject === null)
        <flux:callout icon="cursor-arrow-rays">
            <flux:callout.heading>{{ __('Select a project') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Pick a project to see its change requests.') }}</flux:callout.text>
        </flux:callout>
    @else
        <x-data-table
            :title="__('Change requests')"
            :count="$this->changeRequests->count()"
            :count-label="__('total')"
            :empty="$this->changeRequests->isEmpty()"
            :empty-message="__('No change requests yet.')">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Title') }}</flux:table.column>
                    <flux:table.column>{{ __('Category') }}</flux:table.column>
                    <flux:table.column>{{ __('Priority') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Decision') }}</flux:table.column>
                    <flux:table.column>{{ __('Requester') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->changeRequests as $cr)
                        <flux:table.row>
                            <flux:table.cell>
                                <a href="{{ route('change-requests.show', $cr) }}" wire:navigate class="font-medium hover:underline">
                                    <span class="font-mono text-zinc-500 dark:text-zinc-400">{{ $cr->reference() }}</span>
                                    {{ $cr->title }}
                                </a>
                                @if ($cr->description)
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ \Illuminate\Support\Str::limit($cr->description, 100) }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="zinc" size="sm">{{ EnumLabel::lower($cr->category) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::changeRequestPriority($cr->priority)" size="sm">{{ EnumLabel::lower($cr->priority) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::changeRequestStatus($cr->status)" size="sm">{{ EnumLabel::lower($cr->status) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($cr->decision)
                                    <flux:badge :color="BadgeVariant::changeRequestDecision($cr->decision)" size="sm">{{ EnumLabel::lower($cr->decision) }}</flux:badge>
                                    @if ($cr->decided_at)
                                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $cr->decided_at->format('Y-m-d') }}</div>
                                    @endif
                                @else
                                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('pending') }}</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $cr->requesterRole?->name ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif
</div>
