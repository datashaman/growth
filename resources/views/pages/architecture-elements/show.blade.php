<?php

use App\Models\DesignElement;
use App\Support\BadgeVariant;
use App\Support\EnumLabel;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component {
    public DesignElement $element;

    public function mount(DesignElement $element): void
    {
        $this->element = $element->load([
            'view.project',
            'view.elements',
            'view.concerns.raisedBy',
            'view.citations.source',
        ]);
    }

    public function rendering(View $view): void
    {
        $view->title($this->element->name);
    }

    /**
     * @return array{reference:mixed, element:DesignElement|null}
     */
    public function relationshipEndpoint(string $endpoint): array
    {
        $reference = match ($endpoint) {
            'source' => data_get($this->element->properties, 'from')
                ?? data_get($this->element->properties, 'source')
                ?? data_get($this->element->properties, 'source_id'),
            'target' => data_get($this->element->properties, 'to')
                ?? data_get($this->element->properties, 'target')
                ?? data_get($this->element->properties, 'target_id'),
            default => null,
        };

        return [
            'reference' => $reference,
            'element' => $this->resolveEndpointElement($reference),
        ];
    }

    /**
     * @return list<array{key:string, value:string}>
     */
    public function propertyRows(): array
    {
        $properties = $this->element->properties ?? [];

        if (! is_array($properties)) {
            return [];
        }

        return collect($properties)
            ->reject(fn (mixed $value, string|int $key): bool => $this->isRelationshipEndpointProperty((string) $key))
            ->map(fn (mixed $value, string|int $key): array => [
                'key' => Str::headline((string) $key),
                'value' => $this->displayPropertyValue($value),
            ])
            ->values()
            ->all();
    }

    private function resolveEndpointElement(mixed $reference): ?DesignElement
    {
        $referenceKey = $this->endpointKey($reference);

        if ($referenceKey === '') {
            return null;
        }

        return $this->element->view->elements
            ->first(fn (DesignElement $candidate): bool => in_array($referenceKey, [
                $this->endpointKey($candidate->getKey()),
                $this->endpointKey($candidate->name),
            ], true));
    }

    public function displayPropertyValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '—';
        }

        return filled($value) ? (string) $value : '—';
    }

    private function endpointKey(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return mb_strtolower(trim((string) $value));
    }

    private function isRelationshipEndpointProperty(string $key): bool
    {
        return $this->element->kind === 'relationship'
            && in_array($key, ['from', 'source', 'source_id', 'to', 'target', 'target_id'], true);
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

    @if ($element->kind === 'relationship')
        @php
            $sourceEndpoint = $this->relationshipEndpoint('source');
            $targetEndpoint = $this->relationshipEndpoint('target');
        @endphp

        <x-data-table :title="__('Relationship endpoints')">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Endpoint') }}</flux:table.column>
                    <flux:table.column>{{ __('Element') }}</flux:table.column>
                    <flux:table.column>{{ __('Reference') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ([__('Source') => $sourceEndpoint, __('Target') => $targetEndpoint] as $label => $endpoint)
                        <flux:table.row>
                            <flux:table.cell class="whitespace-nowrap font-medium">{{ $label }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($endpoint['element'])
                                    <a href="{{ route('architecture-elements.show', $endpoint['element']) }}" wire:navigate class="font-medium hover:underline">
                                        {{ $endpoint['element']->name }}
                                    </a>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="break-all text-zinc-600 dark:text-zinc-300">{{ $this->displayPropertyValue($endpoint['reference']) }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif

    @if ($this->propertyRows() !== [])
        <x-data-table :title="__('Properties')">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column class="w-1/4">{{ __('Property') }}</flux:table.column>
                    <flux:table.column>{{ __('Value') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->propertyRows() as $property)
                        <flux:table.row>
                            <flux:table.cell class="whitespace-nowrap font-medium">{{ $property['key'] }}</flux:table.cell>
                            <flux:table.cell>
                                <pre class="whitespace-pre-wrap break-words font-sans text-sm text-zinc-700 dark:text-zinc-200">{{ $property['value'] }}</pre>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif

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
