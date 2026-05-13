@props([
    'title',
    'description' => null,
    'options',
])

<header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <flux:heading size="xl">{{ $title }}</flux:heading>
        @if ($description)
            <flux:text class="mt-1">{{ $description }}</flux:text>
        @endif
    </div>
    <div class="sm:w-72">
        <flux:select wire:model.live="selectedProjectId" :placeholder="__('Select a project')">
            @foreach ($options as $option)
                <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>
</header>
