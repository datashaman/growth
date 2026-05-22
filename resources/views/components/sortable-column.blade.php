@props([
    'label',
    'field',
    'sort' => null,
    'direction' => 'asc',
])

<flux:table.column {{ $attributes }}>
    <button type="button" wire:click="sortBy('{{ $field }}')" class="inline-flex items-center gap-1 whitespace-nowrap font-medium hover:underline">
        <span>{{ $label }}</span>
        @if ($sort === $field)
            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $direction === 'asc' ? '^' : 'v' }}</span>
        @else
            <span class="text-xs text-zinc-300 dark:text-zinc-600">^</span>
        @endif
    </button>
</flux:table.column>
