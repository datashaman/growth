<?php

use App\Concerns\ProjectScoped;
use App\Support\BadgeVariant;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Intent')] class extends Component {
    use ProjectScoped;

    #[On('stakeholder-saved')]
    public function refreshStakeholders(): void
    {
        unset($this->stakeholders);
    }

    #[On('concern-saved')]
    public function refreshConcerns(): void
    {
        unset($this->concerns);
    }

    #[Computed]
    public function stakeholders()
    {
        return $this->selectedProject?->stakeholders()->orderBy('name')->get() ?? collect();
    }

    #[Computed]
    public function concerns()
    {
        return $this->selectedProject?->concerns()->with('raisedBy')->orderBy('created_at', 'desc')->get() ?? collect();
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
            <x-slot:actions>
                <flux:modal.trigger name="create-stakeholder">
                    <flux:button size="sm" icon="plus" variant="primary">{{ __('New stakeholder') }}</flux:button>
                </flux:modal.trigger>
            </x-slot:actions>
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Role') }}</flux:table.column>
                    <flux:table.column>{{ __('Kind') }}</flux:table.column>
                    <flux:table.column>{{ __('Description') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->stakeholders as $stakeholder)
                        <flux:table.row>
                            <flux:table.cell class="font-medium">{{ $stakeholder->name }}</flux:table.cell>
                            <flux:table.cell>{{ $stakeholder->role ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::stakeholderKind($stakeholder->kind)" size="sm">{{ $stakeholder->kind }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $stakeholder->description ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end gap-1">
                                    <flux:button size="xs" icon="pencil-square" variant="ghost"
                                        wire:click="$dispatch('edit-stakeholder', { stakeholderId: '{{ $stakeholder->id }}' })" />
                                    <flux:button size="xs" icon="trash" variant="ghost"
                                        wire:click="$dispatch('delete-stakeholder', { stakeholderId: '{{ $stakeholder->id }}' })" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>

        <livewire:pages::stakeholders.create-modal :project-id="$this->selectedProject->id" :key="'create-stakeholder-'.$this->selectedProject->id" />
        <livewire:pages::stakeholders.edit-modal :key="'edit-stakeholder-'.$this->selectedProject->id" />
        <livewire:pages::stakeholders.delete-modal :key="'delete-stakeholder-'.$this->selectedProject->id" />

        <x-data-table
            :title="__('Concerns')"
            :count="$this->concerns->count()"
            :count-label="__('raised')"
            :empty="$this->concerns->isEmpty()"
            :empty-message="__('No concerns raised.')">
            <x-slot:actions>
                <flux:modal.trigger name="create-concern">
                    <flux:button size="sm" icon="plus" variant="primary">{{ __('New concern') }}</flux:button>
                </flux:modal.trigger>
            </x-slot:actions>
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Concern') }}</flux:table.column>
                    <flux:table.column>{{ __('Raised by') }}</flux:table.column>
                    <flux:table.column>{{ __('Viewpoint hints') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->concerns as $concern)
                        <flux:table.row>
                            <flux:table.cell>{{ $concern->text }}</flux:table.cell>
                            <flux:table.cell>{{ $concern->raisedBy?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($concern->viewpoint_hints)
                                    {{ is_array($concern->viewpoint_hints) ? implode(', ', $concern->viewpoint_hints) : $concern->viewpoint_hints }}
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end gap-1">
                                    <flux:button size="xs" icon="pencil-square" variant="ghost"
                                        wire:click="$dispatch('edit-concern', { concernId: '{{ $concern->id }}' })" />
                                    <flux:button size="xs" icon="trash" variant="ghost"
                                        wire:click="$dispatch('delete-concern', { concernId: '{{ $concern->id }}' })" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>

        <livewire:pages::concerns.create-modal :project-id="$this->selectedProject->id" :key="'create-concern-'.$this->selectedProject->id" />
        <livewire:pages::concerns.edit-modal :key="'edit-concern-'.$this->selectedProject->id" />
        <livewire:pages::concerns.delete-modal :key="'delete-concern-'.$this->selectedProject->id" />
    @endif
</div>
