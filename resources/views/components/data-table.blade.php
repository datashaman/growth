@props([
    'title' => null,
    'count' => null,
    'countLabel' => null,
    'empty' => false,
    'emptyMessage' => null,
])

<section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
    @isset($header)
        <div class="mb-3 flex items-start justify-between gap-4">
            {{ $header }}
        </div>
    @else
        <div class="mb-3 flex items-center justify-between">
            <flux:heading size="lg">{{ $title }}</flux:heading>
            @if ($count !== null)
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $count }}@if ($countLabel) {{ $countLabel }}@endif
                </flux:text>
            @endif
        </div>
    @endisset

    @if ($empty)
        <flux:text>{{ $emptyMessage ?? __('Nothing to show.') }}</flux:text>
    @else
        {{ $slot }}
    @endif
</section>
