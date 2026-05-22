<?php

use App\Concerns\ProjectScoped;
use App\Support\BadgeVariant;
use App\Support\EnumLabel;
use App\Support\TableColumn;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Architecture')] class extends Component {
    use ProjectScoped;

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
        :description="__('Design views and their elements satisfying stakeholder concerns.')" />


    @if ($this->selectedProject === null)
        <flux:callout icon="cursor-arrow-rays">
            <flux:callout.heading>{{ __('Select a project') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Pick a project to see its design views.') }}</flux:callout.text>
        </flux:callout>
    @else
        @if ($this->designViews->isEmpty())
            <flux:callout icon="information-circle">
                <flux:callout.heading>{{ __('No design views yet') }}</flux:callout.heading>
                <flux:callout.text>{{ __('Add one with the upsert-architecture-view MCP tool.') }}</flux:callout.text>
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
                    </div>
                </x-slot:header>

                {{-- Shared leading-column widths keep the element tables aligned across
                     views (see #376); empty Type/Purpose columns are hidden per #362. --}}
                @php($showType = TableColumn::hasValues($view->elements, fn ($element) => $element->type))
                @php($showPurpose = TableColumn::hasValues($view->elements, fn ($element) => $element->purpose))
                <flux:table class="[&_td]:align-top">
                    <flux:table.columns>
                        <flux:table.column class="w-1/4">{{ __('Element') }}</flux:table.column>
                        <flux:table.column class="w-1/6">{{ __('Kind') }}</flux:table.column>
                        @if ($showType)
                            <flux:table.column class="w-1/6">{{ __('Type') }}</flux:table.column>
                        @endif
                        @if ($showPurpose)
                            <flux:table.column>{{ __('Purpose') }}</flux:table.column>
                        @endif
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($view->elements as $element)
                            <flux:table.row>
                                <flux:table.cell class="font-medium">
                                    <a href="{{ route('architecture-elements.show', $element) }}" wire:navigate class="hover:underline">{{ $element->name }}</a>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="BadgeVariant::designElementKind($element->kind)" size="sm">{{ EnumLabel::lower($element->kind) }}</flux:badge>
                                </flux:table.cell>
                                @if ($showType)
                                    <flux:table.cell>{{ $element->type ?? '—' }}</flux:table.cell>
                                @endif
                                @if ($showPurpose)
                                    <flux:table.cell>{{ $element->purpose ?? '—' }}</flux:table.cell>
                                @endif
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
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Concerns') }}</flux:table.column>
                    <flux:table.column>{{ __('Element types') }}</flux:table.column>
                    <flux:table.column>{{ __('Languages') }}</flux:table.column>
                    <flux:table.column>{{ __('Source') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->customViewpoints as $viewpoint)
                        <flux:table.row>
                            <flux:table.cell class="font-medium">{{ $viewpoint->name }}</flux:table.cell>
                            <flux:table.cell>{{ implode(', ', $viewpoint->concerns ?? []) }}</flux:table.cell>
                            <flux:table.cell>{{ implode(', ', $viewpoint->element_types ?? []) }}</flux:table.cell>
                            <flux:table.cell>{{ implode(', ', $viewpoint->languages ?? []) }}</flux:table.cell>
                            <flux:table.cell>{{ $viewpoint->source ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif
</div>
