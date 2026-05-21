<?php

use App\Concerns\ProjectScoped;
use App\Support\BadgeVariant;
use App\Support\EnumLabel;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Reviews')] class extends Component {
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
        unset($this->reviews);
    }

    #[Computed]
    public function reviews()
    {
        return $this->selectedProject?->reviews()
            ->with('ownerRole')
            ->orderByRaw('held_at IS NULL')
            ->orderByDesc('held_at')
            ->orderByRaw('planned_at IS NULL')
            ->orderByDesc('planned_at')
            ->orderByDesc('id')
            ->get()
            ?? collect();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-project-page-header
        :title="__('Reviews')"
        :description="__('Formal reviews of the project — their status, outcome, and owner.')" />

    @if ($this->selectedProject === null)
        <flux:callout icon="cursor-arrow-rays">
            <flux:callout.heading>{{ __('Select a project') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Pick a project to see its reviews.') }}</flux:callout.text>
        </flux:callout>
    @else
        <x-data-table
            :title="__('Reviews')"
            :count="$this->reviews->count()"
            :count-label="__('total')"
            :empty="$this->reviews->isEmpty()"
            :empty-message="__('No reviews yet.')">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Title') }}</flux:table.column>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Decision') }}</flux:table.column>
                    <flux:table.column>{{ __('Owner') }}</flux:table.column>
                    <flux:table.column>{{ __('Held') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->reviews as $review)
                        <flux:table.row>
                            <flux:table.cell>
                                <a href="{{ route('reviews.show', $review) }}" wire:navigate class="font-medium hover:underline">{{ $review->title }}</a>
                                @if ($review->objective)
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ \Illuminate\Support\Str::limit($review->objective, 100) }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="zinc" size="sm">{{ EnumLabel::lower($review->type) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::reviewStatus($review->status)" size="sm">{{ EnumLabel::lower($review->status) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($review->decision)
                                    <flux:badge :color="BadgeVariant::reviewDecision($review->decision)" size="sm">{{ EnumLabel::lower($review->decision) }}</flux:badge>
                                @else
                                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('pending') }}</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $review->ownerRole?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $review->held_at?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif
</div>
