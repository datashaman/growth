<?php

use App\Models\DesignElement;
use App\Support\BadgeVariant;
use App\Support\EnumLabel;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component {
    public DesignElement $element;

    public function mount(DesignElement $element): void
    {
        $this->element = $element->load([
            'view.project',
            'view.concerns.raisedBy',
            'view.citations.source',
        ]);
    }

    public function rendering(View $view): void
    {
        $view->title($this->element->name);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-detail-page-header
        :title="$element->name"
        :back-href="route('architecture', ['project' => $element->view->project_id])"
        :back-label="__('Back to architecture')">
        <x-slot:badges>
            <flux:badge :color="BadgeVariant::designElementKind($element->kind)" size="sm">{{ EnumLabel::lower($element->kind) }}</flux:badge>
            @if ($element->type)
                <flux:badge color="zinc" size="sm">{{ $element->type }}</flux:badge>
            @endif
        </x-slot:badges>
        <x-slot:description>
            {{ __('Element in design view') }}
            <a href="{{ route('architecture', ['project' => $element->view->project_id]) }}" wire:navigate class="underline">{{ $element->view->name }}</a>
        </x-slot:description>
    </x-detail-page-header>

    <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg" class="mb-3">{{ __('Purpose') }}</flux:heading>
        <flux:text class="whitespace-pre-line">{{ $element->purpose ?? '—' }}</flux:text>
    </section>

    @if ($element->view->concerns->isNotEmpty())
        <x-data-table
            :title="__('Concerns framed by this view')"
            :count="$element->view->concerns->count()">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Concern') }}</flux:table.column>
                    <flux:table.column>{{ __('Raised by') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($element->view->concerns as $concern)
                        <flux:table.row>
                            <flux:table.cell>{{ $concern->text }}</flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">{{ $concern->raisedBy?->name ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif

    @if ($element->view->citations->isNotEmpty())
        <x-data-table
            :title="__('Citations on this view')"
            :count="$element->view->citations->count()">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Source') }}</flux:table.column>
                    <flux:table.column>{{ __('Quote') }}</flux:table.column>
                    <flux:table.column>{{ __('Locator') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($element->view->citations as $citation)
                        <flux:table.row>
                            <flux:table.cell class="font-medium">{{ $citation->source?->title ?? '—' }}</flux:table.cell>
                            <flux:table.cell class="whitespace-normal break-words">{{ $citation->quote ?? '—' }}</flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">{{ $citation->locator ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif
</div>
