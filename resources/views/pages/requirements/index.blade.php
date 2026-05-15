<?php

use App\Concerns\ProjectScoped;
use App\Support\BadgeVariant;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Requirements')] class extends Component {
    use ProjectScoped;

    #[Computed]
    public function requirements()
    {
        return $this->selectedProject?->requirements()
            ->orderBy('doc')
            ->orderBy('created_at')
            ->get()
            ?? collect();
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
            :empty-message="__('No requirements captured.')">
            <x-slot:actions>
                <flux:button size="sm" icon="plus" variant="primary"
                    :href="route('requirements.create', ['project' => $this->selectedProject->id])" wire:navigate>
                    {{ __('New requirement') }}
                </flux:button>
            </x-slot:actions>
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Statement') }}</flux:table.column>
                    <flux:table.column>{{ __('Doc') }}</flux:table.column>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                    <flux:table.column>{{ __('Priority') }}</flux:table.column>
                    <flux:table.column>{{ __('Source') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->requirements as $requirement)
                        <flux:table.row>
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
                            <flux:table.cell>{{ $requirement->source ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif
</div>
