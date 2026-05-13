@props([
    'title',
    'description' => null,
])

<header class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ $title }}</flux:heading>
        @if ($description)
            <flux:text>{{ $description }}</flux:text>
        @endif
    </div>
    @isset($actions)
        <div class="flex items-center gap-2">{{ $actions }}</div>
    @endisset
</header>
