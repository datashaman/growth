<?php

use App\Concerns\ProjectScoped;
use App\Models\Theme;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Themes')] class extends Component {
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
        unset($this->themes);
    }

    /**
     * @return Collection<int,Theme>
     */
    #[Computed]
    public function themes(): Collection
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
     * @return array<string,string>
     */
    public function themePreviewColors(Theme $theme): array
    {
        $tokens = $theme->normalizedCssTokens();

        $colors = [
            'surface' => $this->safePreviewColor($tokens['surface'] ?? null, '#111827'),
            'surface-muted' => $this->safePreviewColor($tokens['surface-muted'] ?? null, '#1f2937'),
            'panel' => $this->safePreviewColor($tokens['panel'] ?? null, '#ffffff'),
            'panel-muted' => $this->safePreviewColor($tokens['panel-muted'] ?? null, '#d1d5db'),
            'text' => $this->safePreviewColor($tokens['text'] ?? null, '#111827'),
            'accent' => $this->safePreviewColor($tokens['accent'] ?? null, '#2563eb'),
            'accent-strong' => $this->safePreviewColor($tokens['accent-strong'] ?? null, $tokens['accent'] ?? '#1d4ed8'),
            'warning' => $this->safePreviewColor($tokens['warning'] ?? null, '#f59e0b'),
        ];

        return $colors;
    }

    private function safePreviewColor(mixed $value, string $fallback): string
    {
        if (! is_scalar($value) && $value !== null) {
            return $fallback;
        }

        $color = trim((string) $value);

        if ($color === '') {
            return $fallback;
        }

        $colorPattern = '/\A(?:#[0-9a-fA-F]{3,8}|(?:rgb|rgba|hsl|hsla|oklch|oklab|lab|lch)\([0-9a-zA-Z\s%,.\/+-]+\)|[a-zA-Z]+)\z/';

        return preg_match($colorPattern, $color) === 1 ? $color : $fallback;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-project-page-header
        :title="__('Themes')"
        :description="__('Project design languages managed through MCP and used by mockup previews.')">
        @if ($this->selectedProject !== null)
            <x-slot:actions>
                <flux:badge color="zinc" size="sm">{{ $this->themes->count() }} {{ __('themes') }}</flux:badge>
            </x-slot:actions>
        @endif
    </x-project-page-header>

    @if ($this->selectedProject === null)
        <flux:callout icon="cursor-arrow-rays">
            <flux:callout.heading>{{ __('Select a project') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Pick a project to review its themes.') }}</flux:callout.text>
        </flux:callout>
    @else
        <x-data-table
            :title="__('Themes')"
            :count="$this->themes->count()"
            :count-label="__('themes')"
            :empty="$this->themes->isEmpty()"
            :empty-message="__('No themes have been created for this project yet. Create and manage themes through the MCP theme tools.')">
            <div class="grid gap-3" data-test="themes-list">
                @foreach ($this->themes as $theme)
                    @php
                        $tokens = $theme->normalizedCssTokens();
                        $previewColors = $this->themePreviewColors($theme);
                    @endphp

                    <article class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-950/40" data-test="theme-card">
                        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_24rem]">
                            <div class="min-w-0">
                                <div class="mb-2 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <flux:heading size="lg">{{ $theme->name }}</flux:heading>
                                            <flux:badge color="zinc" size="sm" class="font-mono">{{ $theme->slug }}</flux:badge>
                                            @if ($theme->is_default)
                                                <flux:badge color="sky" size="sm">{{ __('default') }}</flux:badge>
                                            @endif
                                        </div>
                                        @if ($theme->description)
                                            <flux:text class="mt-1 text-sm">{{ $theme->description }}</flux:text>
                                        @endif
                                    </div>
                                    <div class="flex shrink-0 flex-wrap gap-2">
                                        <flux:badge color="zinc" size="sm">{{ count($tokens) }} {{ __('tokens') }}</flux:badge>
                                        <flux:badge :color="filled($theme->raw_css) ? 'green' : 'zinc'" size="sm">{{ filled($theme->raw_css) ? __('CSS') : __('no CSS') }}</flux:badge>
                                    </div>
                                </div>

                                @if ($theme->design_notes)
                                    <div class="mt-3 rounded-md border border-zinc-200 bg-white p-3 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                                        {{ $theme->design_notes }}
                                    </div>
                                @endif

                                @if ($tokens !== [])
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach (array_keys($tokens) as $token)
                                            <code class="rounded border border-zinc-200 bg-white px-1.5 py-0.5 text-xs text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">--{{ $token }}</code>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div class="overflow-hidden rounded-md border border-zinc-200 shadow-sm dark:border-zinc-700" data-test="theme-preview" style="background: linear-gradient(135deg, {{ $previewColors['surface'] }}, {{ $previewColors['surface-muted'] }}); color: {{ $previewColors['text'] }};">
                                <div class="flex items-center justify-between gap-2 px-4 py-3">
                                    <div class="min-w-0">
                                        <div class="text-xs font-semibold uppercase opacity-70">{{ __('Telemetry') }}</div>
                                        <div class="truncate text-sm font-semibold">{{ $theme->name }}</div>
                                    </div>
                                    <div class="rounded-full px-2 py-1 text-xs font-semibold text-white" style="background-color: {{ $previewColors['accent-strong'] }};">
                                        {{ __('Live') }}
                                    </div>
                                </div>

                                <div class="grid grid-cols-[1fr_5rem] gap-3 px-4 pb-4">
                                    <div class="min-w-0 rounded border p-3" style="background-color: {{ $previewColors['panel'] }}; border-color: {{ $previewColors['panel-muted'] }};">
                                        <div class="mb-3 h-2 rounded-full" style="background: linear-gradient(90deg, {{ $previewColors['accent-strong'] }}, {{ $previewColors['accent'] }});"></div>
                                        <div class="grid grid-cols-3 gap-2">
                                            <div class="h-9 rounded" style="background-color: {{ $previewColors['panel-muted'] }};"></div>
                                            <div class="h-9 rounded" style="background-color: {{ $previewColors['accent'] }};"></div>
                                            <div class="h-9 rounded" style="background-color: {{ $previewColors['warning'] }};"></div>
                                        </div>
                                    </div>
                                    <div class="grid content-between gap-2">
                                        @foreach (['surface', 'panel', 'accent', 'warning'] as $swatch)
                                            <div class="h-8 rounded border border-black/10" title="--{{ $swatch }}" style="background-color: {{ $previewColors[$swatch] }};"></div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="grid grid-cols-4 border-t border-black/10 text-[10px] font-medium uppercase">
                                    @foreach (['surface', 'panel', 'accent', 'warn'] as $label)
                                        <span class="truncate px-2 py-2 opacity-75">{{ $label }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </x-data-table>
    @endif
</div>
