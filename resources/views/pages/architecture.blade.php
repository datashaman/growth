<?php

use App\Concerns\ProjectScoped;
use App\Support\BadgeVariant;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Architecture')] class extends Component {
    use ProjectScoped;

    #[On('design-view-saved')]
    #[On('design-element-saved')]
    public function refreshDesignViews(): void
    {
        unset($this->designViews);
    }

    #[On('custom-viewpoint-saved')]
    public function refreshCustomViewpoints(): void
    {
        unset($this->customViewpoints);
    }

    #[Computed]
    public function designViews()
    {
        return $this->selectedProject?->designViews()
            ->with('elements')
            ->orderBy('viewpoint')
            ->orderBy('name')
            ->get()
            ?? collect();
    }

    #[Computed]
    public function customViewpoints()
    {
        return $this->selectedProject?->customViewpoints()
            ->orderBy('name')
            ->get()
            ?? collect();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-project-page-header
        :title="__('Architecture')"
        :description="__('Design views and their elements satisfying stakeholder concerns.')">
        @if ($this->selectedProject)
            <x-slot:actions>
                <flux:modal.trigger name="create-design-view">
                    <flux:button size="sm" icon="plus" variant="primary">{{ __('New view') }}</flux:button>
                </flux:modal.trigger>
            </x-slot:actions>
        @endif
    </x-project-page-header>

    @if ($this->selectedProject === null)
        <flux:callout icon="cursor-arrow-rays">
            <flux:callout.heading>{{ __('Select a project') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Pick a project to see its design views.') }}</flux:callout.text>
        </flux:callout>
    @else
        @if ($this->designViews->isEmpty())
            <flux:callout icon="information-circle">
                <flux:callout.heading>{{ __('No design views yet') }}</flux:callout.heading>
                <flux:callout.text>{{ __('Create one with the “New view” button above.') }}</flux:callout.text>
            </flux:callout>
        @endif

        @foreach ($this->designViews as $view)
            <x-data-table
                :empty="$view->elements->isEmpty()"
                :empty-message="__('No elements in this view.')">
                <x-slot:header>
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">{{ $view->name }}</flux:heading>
                            <flux:badge color="zinc" size="sm">{{ $view->viewpoint }}</flux:badge>
                        </div>
                        @if ($view->description)
                            <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $view->description }}</flux:text>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $view->elements->count() }} {{ __('elements') }}</flux:text>
                        <flux:button size="xs" icon="plus" variant="primary"
                            wire:click="$dispatch('create-design-element', { designViewId: '{{ $view->id }}' })">
                            {{ __('Element') }}
                        </flux:button>
                        <flux:button size="xs" icon="pencil-square" variant="ghost"
                            wire:click="$dispatch('edit-design-view', { designViewId: '{{ $view->id }}' })" />
                        <flux:button size="xs" icon="trash" variant="ghost"
                            wire:click="$dispatch('delete-design-view', { designViewId: '{{ $view->id }}' })" />
                    </div>
                </x-slot:header>

                <flux:table class="[&_td]:align-top">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Element') }}</flux:table.column>
                        <flux:table.column>{{ __('Kind') }}</flux:table.column>
                        <flux:table.column>{{ __('Type') }}</flux:table.column>
                        <flux:table.column>{{ __('Purpose') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($view->elements as $element)
                            <flux:table.row>
                                <flux:table.cell class="font-medium">{{ $element->name }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="BadgeVariant::designElementKind($element->kind)" size="sm">{{ $element->kind }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>{{ $element->type ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $element->purpose ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex justify-end gap-1">
                                        <flux:button size="xs" icon="pencil-square" variant="ghost"
                                            wire:click="$dispatch('edit-design-element', { designElementId: '{{ $element->id }}' })" />
                                        <flux:button size="xs" icon="trash" variant="ghost"
                                            wire:click="$dispatch('delete-design-element', { designElementId: '{{ $element->id }}' })" />
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </x-data-table>
        @endforeach

        <x-data-table
            :title="__('Custom viewpoints')"
            :count="$this->customViewpoints->count()"
            :count-label="__('defined')"
            :empty="$this->customViewpoints->isEmpty()"
            :empty-message="__('No custom viewpoints. The 12 built-in viewpoints are always available.')">
            <x-slot:actions>
                <flux:modal.trigger name="create-custom-viewpoint">
                    <flux:button size="sm" icon="plus" variant="primary">{{ __('New viewpoint') }}</flux:button>
                </flux:modal.trigger>
            </x-slot:actions>
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Concerns') }}</flux:table.column>
                    <flux:table.column>{{ __('Element types') }}</flux:table.column>
                    <flux:table.column>{{ __('Languages') }}</flux:table.column>
                    <flux:table.column>{{ __('Source') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->customViewpoints as $viewpoint)
                        <flux:table.row>
                            <flux:table.cell class="font-medium">{{ $viewpoint->name }}</flux:table.cell>
                            <flux:table.cell>{{ implode(', ', $viewpoint->concerns ?? []) }}</flux:table.cell>
                            <flux:table.cell>{{ implode(', ', $viewpoint->element_types ?? []) }}</flux:table.cell>
                            <flux:table.cell>{{ implode(', ', $viewpoint->languages ?? []) }}</flux:table.cell>
                            <flux:table.cell>{{ $viewpoint->source ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end gap-1">
                                    <flux:button size="xs" icon="pencil-square" variant="ghost"
                                        wire:click="$dispatch('edit-custom-viewpoint', { customViewpointId: '{{ $viewpoint->id }}' })" />
                                    <flux:button size="xs" icon="trash" variant="ghost"
                                        wire:click="$dispatch('delete-custom-viewpoint', { customViewpointId: '{{ $viewpoint->id }}' })" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>

        <livewire:pages::design-views.create-modal :project-id="$this->selectedProject->id" :key="'create-design-view-'.$this->selectedProject->id" />
        <livewire:pages::design-views.edit-modal :key="'edit-design-view-'.$this->selectedProject->id" />
        <livewire:pages::design-views.delete-modal :key="'delete-design-view-'.$this->selectedProject->id" />

        <livewire:pages::design-elements.create-modal :key="'create-design-element-'.$this->selectedProject->id" />
        <livewire:pages::design-elements.edit-modal :key="'edit-design-element-'.$this->selectedProject->id" />
        <livewire:pages::design-elements.delete-modal :key="'delete-design-element-'.$this->selectedProject->id" />

        <livewire:pages::custom-viewpoints.create-modal :project-id="$this->selectedProject->id" :key="'create-custom-viewpoint-'.$this->selectedProject->id" />
        <livewire:pages::custom-viewpoints.edit-modal :key="'edit-custom-viewpoint-'.$this->selectedProject->id" />
        <livewire:pages::custom-viewpoints.delete-modal :key="'delete-custom-viewpoint-'.$this->selectedProject->id" />
    @endif
</div>
