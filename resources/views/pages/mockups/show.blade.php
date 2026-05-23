<?php

use App\Models\Requirement;
use App\Models\Theme;
use App\Models\SpecMockup;
use App\Models\ThemeAssignment;
use App\Models\WorkItem;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public SpecMockup $mockup;

    /** ULID of the revision currently shown in the iframe. */
    public string $revisionId = '';

    /**
     * Whether the iframe tracks the latest revision. True until the viewer
     * picks a revision by hand; a live update only advances the iframe while
     * it holds.
     */
    public bool $followLatest = true;

    public function mount(SpecMockup $mockup): void
    {
        $this->mockup = $mockup->load([
            'owner.mockups' => fn ($query) => $this->orderMockups($query),
            'owner.project.themeAssignments.theme',
            'owner.project.themes',
            'revisions',
        ]);
        $this->revisionId = (string) ($this->mockup->revisions->last()?->id ?? '');
    }

    public function rendering(View $view): void
    {
        $view->title($this->mockup->name === SpecMockup::DEFAULT_NAME ? __('Default') : $this->mockup->name);
    }

    /**
     * Live refresh: a new revision (or sibling mockup) created via the MCP
     * tools broadcasts a WorkspaceDataChanged event on the workspace channel.
     * Subscribe so the page reflects it without a manual reload.
     *
     * @return array<string,string>
     */
    public function getListeners(): array
    {
        $workspaceId = auth()->user()?->active_workspace_id;

        if ($workspaceId === null) {
            return [];
        }

        return [
            'echo-private:workspaces.'.$workspaceId.',WorkspaceDataChanged' => 'onWorkspaceDataChanged',
        ];
    }

    /**
     * Reload the mockup so a newly arrived revision shows in the nav. The
     * iframe advances to the new revision only while the viewer is still
     * following the latest; a deliberately selected past revision stays put.
     */
    public function onWorkspaceDataChanged(): void
    {
        $fresh = $this->mockup->fresh([
            'owner.mockups' => fn ($query) => $this->orderMockups($query),
            'owner.project.themeAssignments.theme',
            'owner.project.themes',
            'revisions',
        ]);

        if ($fresh === null) {
            return;
        }

        $this->mockup = $fresh;

        if ($this->followLatest) {
            $this->revisionId = (string) ($this->mockup->revisions->last()?->id ?? '');
        }
    }

    /**
     * @return Collection<int,Theme>
     */
    #[Computed]
    public function projectThemes(): Collection
    {
        $projectId = $this->mockup->owner?->project_id;

        if (! is_string($projectId)) {
            return collect();
        }

        return Theme::query()
            ->where('project_id', $projectId)
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
        return $this->mockup->owner?->project?->themeAssignments
            ? $this->mockup->owner->project->themeAssignments->sortBy(['scope_type', 'scope_key'])->values()
            : collect();
    }

    public function themePreviewOverride(): string
    {
        $theme = (string) request()->query('theme', '');

        if ($theme === 'none') {
            return 'none';
        }

        return $this->projectThemes->contains('slug', $theme) ? $theme : '';
    }

    public function assignedThemeSlug(): string
    {
        $owner = $this->mockup->owner;
        $keys = match (true) {
            $owner instanceof WorkItem => [
                ['mockup', $this->mockup->id],
                ['work_item', $owner->id],
                ['work_item', $owner->reference()],
            ],
            $owner instanceof Requirement => [
                ['mockup', $this->mockup->id],
                ['requirement', $owner->id],
                ['requirement', $owner->reference()],
            ],
            default => [
                ['mockup', $this->mockup->id],
            ],
        };

        $assignment = $this->projectThemeAssignments->first(
            fn (ThemeAssignment $assignment): bool => in_array([$assignment->scope_type, $assignment->scope_key], $keys, true),
        );

        return (string) ($assignment?->theme?->slug ?? $this->projectThemes->firstWhere('is_default', true)?->slug ?? '');
    }

    public function mockupPreviewUrl(): string
    {
        $theme = $this->themePreviewOverride() === ''
            ? $this->assignedThemeSlug()
            : $this->themePreviewOverride();

        $parameters = ['mockup' => $this->mockup, 'revision' => $this->revisionId];

        if ($theme !== '' && $theme !== 'none') {
            $parameters['theme'] = $theme;
        }

        return route('mockups.raw', $parameters);
    }

    /**
     * Switch the iframe to a past revision — only if it belongs to this
     * mockup. A hand-picked revision takes the iframe off the latest.
     */
    public function selectRevision(string $revisionId): void
    {
        if ($this->mockup->revisions->contains('id', $revisionId)) {
            $this->revisionId = $revisionId;
            $this->followLatest = $revisionId === (string) ($this->mockup->revisions->last()?->id ?? '');
        }
    }

    /** Route to the owning spec entity's detail page. */
    public function ownerHref(): string
    {
        return $this->mockup->owner instanceof Requirement
            ? route('requirements.show', $this->mockup->owner)
            : route('work-items.show', $this->mockup->owner);
    }

    /** Human label for the owning spec entity. */
    public function ownerLabel(): string
    {
        $owner = $this->mockup->owner;

        return $owner instanceof Requirement
            ? Str::limit($owner->text, 80)
            : $owner->reference().' — '.$owner->name;
    }

    public function ownerBackLabel(): string
    {
        return $this->mockup->owner instanceof Requirement
            ? __('Back to requirement')
            : __('Back to work item');
    }

    private function orderMockups($query): void
    {
        $query
            ->orderByRaw('case when name = ? then 0 else 1 end', [SpecMockup::DEFAULT_NAME])
            ->orderBy('name');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-detail-page-header
        :title="$mockup->name === \App\Models\SpecMockup::DEFAULT_NAME ? __('Default') : $mockup->name"
        :back-href="$this->ownerHref()"
        :back-label="$this->ownerBackLabel()">
        <x-slot:description>
            {{ __('Spec mockup for') }}
            <a href="{{ $this->ownerHref() }}" wire:navigate class="underline">{{ $this->ownerLabel() }}</a>
        </x-slot:description>
        <x-slot:actions>
            <label class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                <span>{{ __('Preview as') }}</span>
                <select
                    class="rounded-md border border-zinc-200 bg-white px-2 py-1 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                    data-growth-theme-selector
                    data-project-id="{{ $mockup->owner->project_id }}"
                    data-current-theme="{{ $this->themePreviewOverride() }}"
                    data-test="mockup-theme-selector">
                    <option value="">{{ __('Assigned/default theme') }}</option>
                    <option value="none" @selected($this->themePreviewOverride() === 'none')>{{ __('No theme') }}</option>
                    @foreach ($this->projectThemes as $theme)
                        <option value="{{ $theme->slug }}" @selected($this->themePreviewOverride() === $theme->slug)>{{ $theme->name }}@if ($theme->is_default) {{ __('(default)') }}@endif</option>
                    @endforeach
                </select>
            </label>
        </x-slot:actions>
    </x-detail-page-header>

    <section class="flex flex-1 flex-col rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        @if ($mockup->owner->mockups->count() > 1 || $mockup->revisions->count() > 1)
            <div class="mb-3 flex flex-col gap-3">
                @if ($mockup->owner->mockups->count() > 1)
                    <div>
                        <nav class="flex flex-wrap gap-2" aria-label="{{ __('Mockups') }}">
                            @foreach ($mockup->owner->mockups as $alternative)
                                @if ($alternative->is($mockup))
                                    <span class="rounded-md bg-sky-600 px-2.5 py-1 text-sm font-medium text-white" aria-current="true">{{ $alternative->name === \App\Models\SpecMockup::DEFAULT_NAME ? __('Default') : $alternative->name }}</span>
                                @else
                                    <a href="{{ route('mockups.show', $alternative) }}" wire:navigate
                                        class="rounded-md bg-zinc-100 px-2.5 py-1 text-sm text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">{{ $alternative->name === \App\Models\SpecMockup::DEFAULT_NAME ? __('Default') : $alternative->name }}</a>
                                @endif
                            @endforeach
                        </nav>
                    </div>
                @endif

                @if ($mockup->revisions->count() > 1)
                    <div>
                        <flux:text class="mb-1 text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Revisions') }}</flux:text>
                        <nav class="flex flex-wrap gap-2" aria-label="{{ __('Revisions') }}">
                            @foreach ($mockup->revisions->sortByDesc('number') as $revision)
                                <button type="button" wire:click="selectRevision('{{ $revision->id }}')"
                                    @class([
                                        'rounded-md px-2.5 py-1 text-sm',
                                        'bg-sky-600 font-medium text-white' => $revision->id === $revisionId,
                                        'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' => $revision->id !== $revisionId,
                                    ])
                                    @if ($revision->id === $revisionId) aria-current="true" @endif>
                                    {{ __('Revision :number', ['number' => $revision->number]) }}@if ($revision->is($mockup->revisions->last())) · {{ __('current') }}@endif
                                </button>
                            @endforeach
                        </nav>
                    </div>
                @endif
            </div>
        @endif

        <iframe
            wire:key="mockup-{{ $mockup->id }}-revision-{{ $revisionId }}"
            src="{{ $this->mockupPreviewUrl() }}"
            data-themed-mockup-frame
            data-src-base="{{ route('mockups.raw', ['mockup' => $mockup, 'revision' => $revisionId]) }}"
            data-assigned-theme="{{ $this->assignedThemeSlug() }}"
            sandbox="allow-scripts"
            loading="lazy"
            title="{{ $mockup->name }}"
            class="h-[70vh] w-full rounded-lg border border-zinc-200 bg-white dark:border-zinc-700"></iframe>
    </section>

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
