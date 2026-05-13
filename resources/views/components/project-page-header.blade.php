@props([
    'title',
    'description' => null,
])

<header class="flex flex-col gap-2">
    <flux:heading size="xl">{{ $title }}</flux:heading>
    @if ($description)
        <flux:text>{{ $description }}</flux:text>
    @endif
</header>
