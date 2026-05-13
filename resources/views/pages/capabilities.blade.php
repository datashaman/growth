<?php

use App\Concerns\ProjectScoped;
use App\Support\BadgeVariant;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Capabilities')] class extends Component {
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
        :title="__('Capabilities')"
        :description="__('Requirements the system must satisfy.')"
        :options="$this->projectOptions" />

    @if ($this->selectedProject === null)
        <flux:callout icon="cursor-arrow-rays">
            <flux:callout.heading>{{ __('Select a project') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Pick a project from the dropdown to see its requirements.') }}</flux:callout.text>
        </flux:callout>
    @else
        <x-data-table
            :title="__('Requirements')"
            :count="$this->requirements->count()"
            :count-label="__('captured')"
            :empty="$this->requirements->isEmpty()"
            :empty-message="__('No requirements captured.')">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Requirement') }}</flux:table.column>
                    <flux:table.column>{{ __('Doc') }}</flux:table.column>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                    <flux:table.column>{{ __('Priority') }}</flux:table.column>
                    <flux:table.column>{{ __('Source') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->requirements as $requirement)
                        <flux:table.row>
                            <flux:table.cell>
                                <div>{{ $requirement->text }}</div>
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
