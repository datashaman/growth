<?php

use App\Concerns\ProjectScoped;
use App\Support\ArchitectureDiagram;
use App\Support\BadgeVariant;
use App\Support\EnumLabel;
use App\Support\TableColumn;
use Illuminate\Support\Str;
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
                @php
                    $diagram = ArchitectureDiagram::fromElements($view->elements);
                    $diagramNodes = $diagram['nodes'];
                    $matchedRelationships = $diagram['relationships'];
                    $unmatchedRelationships = $diagram['unmatched_relationships'];
                    $diagramAnnotations = $view->elements->whereIn('kind', ['attribute', 'constraint'])->values();
                    $showType = TableColumn::hasValues($view->elements, fn ($element) => $element->type);
                    $showPurpose = TableColumn::hasValues($view->elements, fn ($element) => $element->purpose);
                @endphp

                <div class="mb-5 border-y border-zinc-100 py-4 dark:border-zinc-800" aria-label="{{ __('Architecture diagram for :view', ['view' => $view->name]) }}">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Diagram') }}</flux:text>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ count($diagramNodes) }} {{ __('nodes') }} / {{ count($matchedRelationships) }} {{ __('positioned relationships') }}
                        </flux:text>
                    </div>

                    @if ($diagramNodes)
                        <div class="overflow-x-auto rounded-sm border border-zinc-200 bg-zinc-50/70 p-3 dark:border-zinc-700 dark:bg-zinc-950/40">
                            <div class="relative" style="width: {{ $diagram['width'] }}px; height: {{ $diagram['height'] }}px;">
                                <svg
                                    class="absolute inset-0 size-full"
                                    viewBox="0 0 {{ $diagram['width'] }} {{ $diagram['height'] }}"
                                    role="img"
                                    aria-label="{{ __('Positioned architecture relationships for :view', ['view' => $view->name]) }}"
                                >
                                    <defs>
                                        <marker id="architecture-arrow-{{ $view->id }}" markerWidth="10" markerHeight="10" refX="8" refY="3" orient="auto" markerUnits="strokeWidth">
                                            <path d="M0,0 L0,6 L9,3 z" class="fill-zinc-400 dark:fill-zinc-500" />
                                        </marker>
                                    </defs>

                                    @foreach ($matchedRelationships as $edge)
                                        @php
                                            $fromPosition = $edge['from_position'];
                                            $toPosition = $edge['to_position'];
                                            $deltaX = $toPosition['center_x'] - $fromPosition['center_x'];
                                            $deltaY = $toPosition['center_y'] - $fromPosition['center_y'];
                                            $startsHorizontally = abs($deltaX) >= abs($deltaY);
                                            $startX = $startsHorizontally
                                                ? $fromPosition['center_x'] + (($deltaX >= 0 ? 1 : -1) * ($diagram['node_width'] / 2))
                                                : $fromPosition['center_x'];
                                            $startY = $startsHorizontally
                                                ? $fromPosition['center_y']
                                                : $fromPosition['center_y'] + (($deltaY >= 0 ? 1 : -1) * ($diagram['node_height'] / 2));
                                            $endX = $startsHorizontally
                                                ? $toPosition['center_x'] - (($deltaX >= 0 ? 1 : -1) * ($diagram['node_width'] / 2))
                                                : $toPosition['center_x'];
                                            $endY = $startsHorizontally
                                                ? $toPosition['center_y']
                                                : $toPosition['center_y'] - (($deltaY >= 0 ? 1 : -1) * ($diagram['node_height'] / 2));
                                            $midX = ($startX + $endX) / 2;
                                            $midY = ($startY + $endY) / 2;
                                            $controlOffset = $startsHorizontally ? 0 : 42;
                                            $path = $startsHorizontally
                                                ? "M {$startX} {$startY} C {$midX} {$startY}, {$midX} {$endY}, {$endX} {$endY}"
                                                : "M {$startX} {$startY} C ".($startX + $controlOffset)." {$midY}, ".($endX + $controlOffset)." {$midY}, {$endX} {$endY}";
                                        @endphp
                                        <a href="{{ route('architecture-elements.show', $edge['element']) }}" wire:navigate>
                                            <path d="{{ $path }}" fill="none" stroke="currentColor" stroke-width="2" marker-end="url(#architecture-arrow-{{ $view->id }})" class="text-zinc-300 transition hover:text-zinc-500 dark:text-zinc-600 dark:hover:text-zinc-400" />
                                            <rect x="{{ $midX - 70 }}" y="{{ $midY - 13 }}" width="140" height="26" rx="3" class="fill-white/95 stroke-zinc-200 dark:fill-zinc-900/95 dark:stroke-zinc-700" />
                                            <text x="{{ $midX }}" y="{{ $midY + 4 }}" text-anchor="middle" class="fill-zinc-600 text-[11px] font-medium dark:fill-zinc-300">{{ Str::limit($edge['element']->type ?: $edge['element']->name, 22) }}</text>
                                        </a>
                                    @endforeach
                                </svg>

                            @foreach ($diagramNodes as $position)
                                @php($element = $position['element'])
                                <a
                                    href="{{ route('architecture-elements.show', $element) }}"
                                    wire:navigate
                                    class="group absolute z-10 border border-zinc-200 bg-white p-3 shadow-sm transition hover:border-zinc-400 hover:shadow dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-500"
                                    style="left: {{ $position['x'] }}px; top: {{ $position['y'] }}px; width: {{ $diagram['node_width'] }}px; height: {{ $diagram['node_height'] }}px;"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="truncate text-sm font-semibold text-zinc-900 group-hover:underline dark:text-zinc-100">{{ $element->name }}</div>
                                            @if ($element->type)
                                                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $element->type }}</div>
                                            @endif
                                        </div>
                                        <flux:badge :color="BadgeVariant::designElementKind($element->kind)" size="sm">{{ EnumLabel::lower($element->kind) }}</flux:badge>
                                    </div>
                                    @if ($element->purpose)
                                        <p class="mt-3 line-clamp-2 text-xs leading-5 text-zinc-600 dark:text-zinc-300">{{ $element->purpose }}</p>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                        </div>
                    @else
                        <div class="border border-dashed border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            {{ __('No entity nodes in this view yet.') }}
                        </div>
                    @endif

                    @if ($unmatchedRelationships)
                        <div class="mt-4 space-y-2">
                            @foreach ($unmatchedRelationships as $relationship)
                                <a
                                    href="{{ route('architecture-elements.show', $relationship['element']) }}"
                                    wire:navigate
                                    class="grid gap-2 border-l-2 border-zinc-300 bg-zinc-50 px-3 py-2 text-sm hover:bg-white dark:border-zinc-600 dark:bg-zinc-950/40 dark:hover:bg-zinc-900 md:grid-cols-[1fr_auto_1fr]"
                                >
                                    <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $relationship['from'] ?: $relationship['element']->name }}</span>
                                    <span class="hidden text-zinc-400 md:inline">-&gt;</span>
                                    <span class="text-zinc-600 dark:text-zinc-300">{{ $relationship['to'] ?: ($relationship['element']->purpose ?: $relationship['element']->type ?: __('relationship')) }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @if ($diagramAnnotations->isNotEmpty())
                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach ($diagramAnnotations as $annotation)
                                <a href="{{ route('architecture-elements.show', $annotation) }}" wire:navigate class="inline-flex items-center gap-2 border border-zinc-200 px-2.5 py-1 text-xs hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                                    <flux:badge :color="BadgeVariant::designElementKind($annotation->kind)" size="sm">{{ EnumLabel::lower($annotation->kind) }}</flux:badge>
                                    <span>{{ $annotation->name }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

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
