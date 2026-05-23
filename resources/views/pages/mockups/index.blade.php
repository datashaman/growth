<?php

use App\Concerns\ProjectScoped;
use App\Models\Theme;
use App\Models\SpecMockup;
use App\Models\ThemeAssignment;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Mockups')] class extends Component {
    use ProjectScoped;

    /**
     * @return array<string,string>
     */
    public function getListeners(): array
    {
        return $this->projectScopedListeners();
    }

    public function onProjectDataChanged(): void
    {
        unset($this->workItemsWithMockups, $this->missingMockupWorkItems, $this->mockupCount, $this->projectThemes, $this->projectThemeAssignments);
    }

    #[Computed]
    public function mockupCount(): int
    {
        if ($this->selectedProject === null) {
            return 0;
        }

        return SpecMockup::query()
            ->whereHasMorph('owner', [WorkItem::class], function (Builder $query): void {
                $query->where('project_id', $this->selectedProject->id);
            })
            ->count();
    }

    /**
     * @return Collection<int,WorkItem>
     */
    #[Computed]
    public function workItemsWithMockups(): Collection
    {
        if ($this->selectedProject === null) {
            return collect();
        }

        return $this->selectedProject
            ->workItems()
            ->with([
                'mockups' => fn ($query) => $query
                    ->orderByRaw('case when name = ? then 0 else 1 end', [SpecMockup::DEFAULT_NAME])
                    ->orderBy('name')
                    ->with('currentRevision'),
            ])
            ->withCount('mockups')
            ->has('mockups')
            ->orderBy('number')
            ->get();
    }

    /**
     * @return Collection<int,WorkItem>
     */
    #[Computed]
    public function missingMockupWorkItems(): Collection
    {
        if ($this->selectedProject === null) {
            return collect();
        }

        return $this->selectedProject
            ->workItems()
            ->where('needs_mockups', true)
            ->doesntHave('mockups')
            ->orderBy('number')
            ->get(['id', 'number', 'kind', 'name', 'status', 'project_id', 'needs_mockups']);
    }

    /**
     * @return Collection<int,Theme>
     */
    #[Computed]
    public function projectThemes(): Collection
    {
        if ($this->selectedProject === null) {
            return collect();
        }

        return $this->selectedProject
            ->themes()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int,ThemeAssignment>
     */
    #[Computed]
    public function projectThemeAssignments(): Collection
    {
        if ($this->selectedProject === null) {
            return collect();
        }

        return $this->selectedProject
            ->themeAssignments()
            ->with('theme')
            ->orderBy('scope_type')
            ->orderBy('scope_key')
            ->get();
    }

    public function themePreviewOverride(): string
    {
        $theme = (string) request()->query('theme', '');

        if ($theme === 'none') {
            return 'none';
        }

        return $this->projectThemes->contains('slug', $theme) ? $theme : '';
    }

    public function assignedThemeSlug(SpecMockup $mockup): string
    {
        $owner = $mockup->owner;
        $keys = $owner instanceof WorkItem
            ? [
                ['mockup', $mockup->id],
                ['work_item', $owner->id],
                ['work_item', $owner->reference()],
            ]
            : [
                ['mockup', $mockup->id],
            ];

        $assignment = $this->projectThemeAssignments->first(
            fn (ThemeAssignment $assignment): bool => in_array([$assignment->scope_type, $assignment->scope_key], $keys, true),
        );

        return (string) ($assignment?->theme?->slug ?? $this->projectThemes->firstWhere('is_default', true)?->slug ?? '');
    }

    public function mockupPreviewUrl(SpecMockup $mockup): string
    {
        $theme = $this->themePreviewOverride() === ''
            ? $this->assignedThemeSlug($mockup)
            : $this->themePreviewOverride();

        $parameters = ['mockup' => $mockup];

        if ($theme !== '' && $theme !== 'none') {
            $parameters['theme'] = $theme;
        }

        return route('mockups.raw', $parameters);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-project-page-header
        :title="__('Mockups')"
        :description="__('Project-level review of work-item mockups and missing mockup coverage.')">
        @if ($this->selectedProject !== null)
            <x-slot:actions>
                <label class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                    <span>{{ __('Preview as') }}</span>
                    <select
                        class="rounded-md border border-zinc-200 bg-white px-2 py-1 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                        data-growth-theme-selector
                        data-project-id="{{ $this->selectedProject->id }}"
                        data-current-theme="{{ $this->themePreviewOverride() }}"
                        data-test="mockup-theme-selector">
                        <option value="">{{ __('Assigned/default theme') }}</option>
                        <option value="none" @selected($this->themePreviewOverride() === 'none')>{{ __('No theme') }}</option>
                        @foreach ($this->projectThemes as $theme)
                            <option value="{{ $theme->slug }}" @selected($this->themePreviewOverride() === $theme->slug)>{{ $theme->name }}@if ($theme->is_default) {{ __('(default)') }}@endif</option>
                        @endforeach
                    </select>
                </label>
                <flux:badge color="zinc" size="sm">{{ $this->mockupCount }} {{ __('mockups') }}</flux:badge>
            </x-slot:actions>
        @endif
    </x-project-page-header>

    @if ($this->selectedProject === null)
        <flux:callout icon="cursor-arrow-rays">
            <flux:callout.heading>{{ __('Select a project') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Pick a project to review its mockups.') }}</flux:callout.text>
        </flux:callout>
    @else
        @if ($this->missingMockupWorkItems->isNotEmpty())
            <section class="rounded-xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900/50 dark:bg-amber-950/30">
                <div class="mb-3 flex min-w-0 items-baseline gap-3">
                    <flux:heading size="lg">{{ __('Missing mockup coverage') }}</flux:heading>
                    <flux:text class="text-sm text-amber-800 dark:text-amber-300">{{ $this->missingMockupWorkItems->count() }} {{ __('items') }}</flux:text>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->missingMockupWorkItems as $workItem)
                        <a href="{{ route('work-items.show', $workItem) }}" wire:navigate class="inline-flex max-w-full items-center gap-2 rounded-md border border-amber-300 bg-white px-2.5 py-1.5 text-sm text-amber-950 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/70 dark:text-amber-100 dark:hover:bg-amber-900/70">
                            <span class="shrink-0 font-mono text-xs font-semibold">{{ $workItem->reference() }}</span>
                            <span class="min-w-0 truncate">{{ $workItem->name }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <x-data-table
            :title="__('Work-item mockups')"
            :count="$this->mockupCount"
            :count-label="__('mockups')"
            :empty="$this->workItemsWithMockups->isEmpty()"
            :empty-message="__('No work-item mockups have been created for this project yet.')">
            <div class="grid gap-5" data-test="project-mockup-groups">
                @foreach ($this->workItemsWithMockups as $workItem)
                    <article class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-950/40" data-test="project-mockup-group">
                        <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <a href="{{ route('work-items.show', $workItem) }}" wire:navigate class="inline-flex max-w-full items-center gap-2 hover:underline">
                                    <span class="shrink-0 rounded border border-zinc-200 bg-white px-1.5 py-0.5 font-mono text-xs font-semibold text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/70 dark:text-zinc-400">{{ $workItem->reference() }}</span>
                                    <span class="min-w-0 truncate font-medium text-zinc-900 dark:text-zinc-100">{{ $workItem->name }}</span>
                                </a>
                            </div>
                            <flux:badge color="zinc" size="sm">{{ $workItem->mockups_count }} {{ __('mockups') }}</flux:badge>
                        </div>

                        <div class="flex gap-3 overflow-x-auto pb-1" data-test="project-mockup-strip">
                            @foreach ($workItem->mockups as $mockup)
                                <a href="{{ route('mockups.show', $mockup) }}" wire:navigate class="group block w-72 shrink-0 rounded-lg border border-zinc-200 bg-white p-2 transition hover:border-sky-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-sky-700" data-test="project-mockup-card">
                                    <div class="h-40 overflow-hidden rounded-md border border-zinc-200 bg-white dark:border-zinc-700">
                                        <iframe
                                            src="{{ $this->mockupPreviewUrl($mockup) }}"
                                            data-themed-mockup-frame
                                            data-src-base="{{ route('mockups.raw', $mockup) }}"
                                            data-assigned-theme="{{ $this->assignedThemeSlug($mockup) }}"
                                            sandbox="allow-scripts"
                                            loading="lazy"
                                            tabindex="-1"
                                            aria-hidden="true"
                                            title="{{ $mockup->name }}"
                                            class="h-[320px] w-[576px] origin-top-left scale-50 pointer-events-none bg-white"></iframe>
                                    </div>
                                    <div class="mt-2 min-w-0">
                                        <div class="truncate text-sm font-medium text-zinc-900 group-hover:underline dark:text-zinc-100">
                                            {{ $mockup->name === \App\Models\SpecMockup::DEFAULT_NAME ? __('Default') : $mockup->name }}
                                        </div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ __('Revision :number', ['number' => $mockup->currentRevision?->number ?? 0]) }}
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </x-data-table>
    @endif

    @once
        <script>
            window.GrowthMockupThemeObserver = window.GrowthMockupThemeObserver || null;

            window.GrowthApplyMockupTheme = () => {
                const selector = document.querySelector('[data-growth-theme-selector]');

                if (!selector) {
                    return;
                }

                document.querySelectorAll('[data-themed-mockup-frame]').forEach((frame) => {
                    if (!frame.dataset.srcBase) {
                        return;
                    }

                    const url = new URL(frame.dataset.srcBase, window.location.origin);
                    const theme = selector.value === '' ? (frame.dataset.assignedTheme || '') : selector.value;

                    if (theme && theme !== 'none') {
                        url.searchParams.set('theme', theme);
                    } else {
                        url.searchParams.delete('theme');
                    }

                    const nextSrc = url.toString();

                    if (frame.src !== nextSrc) {
                        frame.src = nextSrc;
                    }
                });
            };

            window.GrowthBindMockupThemeSelectors = () => {
                document.querySelectorAll('[data-growth-theme-selector]').forEach((selector) => {
                    if (selector.dataset.growthThemeBound === 'true') {
                        selector.value = selector.dataset.currentTheme || '';
                    } else {
                        selector.dataset.growthThemeBound = 'true';
                        selector.value = selector.dataset.currentTheme || '';

                        selector.addEventListener('change', () => {
                            const url = new URL(window.location.href);
                            if (selector.value === '') {
                                url.searchParams.delete('theme');
                            } else {
                                url.searchParams.set('theme', selector.value);
                            }
                            window.history.replaceState({}, '', url.toString());
                            window.GrowthApplyMockupTheme();
                        });
                    }
                });

                if (window.GrowthMockupThemeObserver) {
                    window.GrowthMockupThemeObserver.disconnect();
                }

                window.GrowthMockupThemeObserver = new MutationObserver(window.GrowthApplyMockupTheme);
                window.GrowthMockupThemeObserver.observe(document.body, { childList: true, subtree: true });
                window.GrowthApplyMockupTheme();
            };

            document.addEventListener('livewire:navigated', window.GrowthBindMockupThemeSelectors);
            document.addEventListener('DOMContentLoaded', window.GrowthBindMockupThemeSelectors);
            window.GrowthBindMockupThemeSelectors();
        </script>
    @endonce
</div>
