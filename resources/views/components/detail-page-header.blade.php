@props([
    'title',
    'backRoute' => null,
    'backHref' => null,
    'backLabel' => null,
])

@php($backUrl = $backHref ?? ($backRoute ? route($backRoute) : null))

<header class="flex flex-col gap-2">
    @if ($backUrl)
        <flux:link :href="$backUrl" wire:navigate variant="ghost" class="inline-flex items-center gap-1 text-sm text-zinc-500 dark:text-zinc-400">
            <flux:icon.arrow-left class="size-3" />
            {{ $backLabel ?? __('Back') }}
        </flux:link>
    @endif

    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <flux:heading size="xl">{{ $title }}</flux:heading>
            @isset($badges)
                <div class="flex items-center gap-2">{{ $badges }}</div>
            @endisset
        </div>
        @isset($actions)
            <div class="flex items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>

    @isset($description)
        <flux:text class="text-zinc-600 dark:text-zinc-400">{{ $description }}</flux:text>
    @endisset
</header>
