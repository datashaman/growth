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
<section class="rounded-xl border border-zinc-200 bg-white p-5 [&_tbody_tr]:transition-colors [&_tbody_tr:hover]:bg-zinc-50 [&_td]:whitespace-normal [&_td]:break-words dark:border-zinc-700 dark:bg-zinc-900 dark:[&_tbody_tr:hover]:bg-zinc-800/50">
    @isset($header)
        <div class="mb-3 flex w-full items-start justify-between gap-4 [&_.data-table-count]:shrink-0 [&_.data-table-count]:whitespace-nowrap">
            {{ $header }}
        </div>
    @else
        <div class="mb-3 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex min-w-0 items-baseline gap-3">
                @if ($title)
                    <flux:heading size="lg">{{ $title }}</flux:heading>
                @endif
                @if ($count !== null)
                    <flux:text class="data-table-count whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $count }}@if ($countLabel) {{ $countLabel }}@endif
                    </flux:text>
                @endif
            </div>
            @if (isset($filters) || isset($actions))
                <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                    @isset($filters)
                        {{ $filters }}
                    @endisset
                    @isset($actions)
                        {{ $actions }}
                    @endisset
                </div>
            @endif
        </div>
    @endisset

    @if ($empty)
        <flux:text>{{ $emptyMessage ?? __('Nothing to show.') }}</flux:text>
    @else
        {{ $slot }}
    @endif
</section>
