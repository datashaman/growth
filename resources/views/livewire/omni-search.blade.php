<?php

use App\Growth\Search\SearchService;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $query = '';

    /**
     * Flat, ranked hits for the current query, each tagged with its global index.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function hits(): array
    {
        if (mb_strlen(trim($this->query)) < 2) {
            return [];
        }

        return app(SearchService::class)
            ->search($this->query)
            ->values()
            ->map(fn ($hit, int $index): array => $hit->toArray() + ['index' => $index])
            ->all();
    }

    /**
     * Hits grouped by entity type for display, preserving rank order.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    #[Computed]
    public function groups(): array
    {
        $groups = [];

        foreach ($this->hits as $hit) {
            $groups[$hit['type']][] = $hit;
        }

        return $groups;
    }
}; ?>

<div
    x-data="{
        open: false,
        selected: 0,
        get count() { return this.$refs.results?.querySelectorAll('[data-omni-index]').length ?? 0 },
        launch() { this.open = true; this.selected = 0; this.$nextTick(() => this.$refs.input?.focus()) },
        move(delta) {
            if (this.count === 0) { return }
            this.selected = (this.selected + delta + this.count) % this.count
            this.$nextTick(() => this.$refs.results
                ?.querySelector(`[data-omni-index='${this.selected}']`)
                ?.scrollIntoView({ block: 'nearest' }))
        },
        choose() { this.$refs.results?.querySelector(`[data-omni-index='${this.selected}']`)?.click() },
    }"
    @keydown.window.cmd.k.prevent="launch()"
    @keydown.window.ctrl.k.prevent="launch()"
>
    <button
        type="button"
        @click="launch()"
        class="flex w-full items-center gap-2 rounded-lg border border-zinc-200 px-3 py-1.5 text-sm text-zinc-500 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800"
    >
        <flux:icon.magnifying-glass class="size-4 shrink-0" />
        <span class="flex-1 text-start">{{ __('Search…') }}</span>
        <kbd class="rounded border border-zinc-300 px-1 text-xs dark:border-zinc-600">⌘K</kbd>
    </button>

    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-[12vh]"
        @keydown.escape.window="open = false"
    >
        <div class="absolute inset-0 bg-zinc-900/50" @click="open = false"></div>

        <div
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('Search') }}"
            class="relative w-full max-w-xl overflow-hidden rounded-xl bg-white shadow-2xl dark:bg-zinc-800"
            @keydown.down.prevent="move(1)"
            @keydown.up.prevent="move(-1)"
            @keydown.enter.prevent="choose()"
        >
            <div class="flex items-center gap-2 border-b border-zinc-200 px-4 dark:border-zinc-700">
                <flux:icon.magnifying-glass class="size-5 shrink-0 text-zinc-400" />
                <input
                    x-ref="input"
                    type="text"
                    wire:model.live.debounce.300ms="query"
                    @input="selected = 0"
                    aria-label="{{ __('Search projects, work items, risks') }}"
                    placeholder="{{ __('Search projects, work items, risks…') }}"
                    class="w-full border-0 bg-transparent py-3 text-sm text-zinc-800 placeholder:text-zinc-400 focus:outline-none focus:ring-0 dark:text-zinc-100"
                />
            </div>

            <div x-ref="results" class="max-h-[60vh] overflow-y-auto pb-2">
                @forelse ($this->groups as $type => $hits)
                    <div class="px-3 pb-1 pt-3 text-xs font-medium uppercase tracking-wide text-zinc-400">
                        {{ \Illuminate\Support\Str::headline(\Illuminate\Support\Str::plural($type)) }}
                    </div>

                    @foreach ($hits as $hit)
                        <a
                            href="{{ $hit['route'] }}"
                            wire:navigate
                            data-omni-index="{{ $hit['index'] }}"
                            @mouseenter="selected = {{ $hit['index'] }}"
                            :class="selected === {{ $hit['index'] }} ? 'bg-zinc-100 dark:bg-zinc-700' : ''"
                            class="flex items-center gap-2 px-3 py-2 text-sm"
                        >
                            <flux:badge size="sm" :color="\App\Support\BadgeVariant::searchType($type)">
                                {{ str_replace('_', ' ', $type) }}
                            </flux:badge>
                            <span class="truncate text-zinc-700 dark:text-zinc-200">{{ $hit['label'] }}</span>
                        </a>
                    @endforeach
                @empty
                    <div class="px-4 py-8 text-center text-sm text-zinc-400">
                        @if (mb_strlen(trim($query)) >= 2)
                            {{ __('No matches.') }}
                        @else
                            {{ __('Type to search across the workspace.') }}
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
