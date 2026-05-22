@props([
    'title' => null,
    'count' => null,
    'countLabel' => null,
    'empty' => false,
    'emptyMessage' => null,
])

{{-- Flux marks the whole table `whitespace-nowrap`; combined with `table-fixed` that lets a long
     cell overflow its column and clip trailing columns off-screen. Override `nowrap` on data cells
     so prose wraps inside its (already fixed-width) column instead. Headers stay nowrap. --}}
<section class="rounded-xl border border-zinc-200 bg-white p-5 [&_td]:whitespace-normal [&_td]:break-words dark:border-zinc-700 dark:bg-zinc-900">
    @isset($header)
        <div class="mb-3 flex items-start justify-between gap-4">
            {{ $header }}
        </div>
    @else
        <div class="mb-3 flex items-center justify-between gap-4">
            <div class="flex items-baseline gap-3">
                @if ($title)
                    <flux:heading size="lg">{{ $title }}</flux:heading>
                @endif
                @if ($count !== null)
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $count }}@if ($countLabel) {{ $countLabel }}@endif
                    </flux:text>
                @endif
            </div>
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endisset

    @if ($empty)
        <flux:text>{{ $emptyMessage ?? __('Nothing to show.') }}</flux:text>
    @else
        {{ $slot }}
    @endif
</section>
