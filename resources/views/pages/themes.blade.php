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

    public function themePreviewHtml(Theme $theme): string
    {
        return <<<'HTML'
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
* { box-sizing: border-box; }
html, body { margin: 0; min-height: 100%; overflow: hidden; }
body {
  background: linear-gradient(180deg, #111827, #1f2937);
  color: #f8fafc;
  font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}
main { min-height: 100vh; padding: 18px; }
.topbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 18px; }
.label { font-size: 11px; font-weight: 700; letter-spacing: .08em; opacity: .72; text-transform: uppercase; }
h1 { margin: 3px 0 0; font-size: 20px; line-height: 1.1; }
.status, button.active {
  border: 1px solid #2563eb;
  border-radius: 999px;
  background: #2563eb;
  color: white;
  font-size: 12px;
  font-weight: 700;
  padding: 7px 11px;
}
.panel {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: white;
  color: #0f172a;
  padding: 14px;
}
.bar { height: 10px; border-radius: 999px; background: linear-gradient(90deg, #1d4ed8, #38bdf8); margin-bottom: 14px; }
.metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.metric { min-height: 52px; border-radius: 6px; background: #e2e8f0; padding: 9px; }
.metric strong { display: block; font-size: 20px; line-height: 1; }
.metric span { display: block; margin-top: 7px; font-size: 10px; opacity: .68; text-transform: uppercase; }
.spark { height: 7px; border-radius: 999px; background: #38bdf8; margin-top: 10px; }
.warn { margin-top: 12px; border: 1px solid #f59e0b; border-radius: 6px; background: #fef3c7; color: #7c2d12; padding: 8px 10px; font-size: 12px; font-weight: 650; }
table { width: 100%; margin-top: 12px; border-collapse: collapse; font-size: 11px; }
th { text-align: left; opacity: .7; }
td, th { padding: 5px 0; border-bottom: 1px solid rgba(100, 116, 139, .22); }
</style>
<style data-growth-theme-preview>
HTML
            ."\n".$theme->cssForInjection()."\n"
            . <<<'HTML'
</style>
</head>
<body>
<main>
  <div class="topbar">
    <div>
      <div class="label">Preview</div>
      <h1>Interface sample</h1>
    </div>
    <button class="active">Live</button>
  </div>
  <section class="panel">
    <div class="bar"></div>
    <div class="metrics">
      <div class="metric"><strong>42</strong><span>primary</span></div>
      <div class="metric"><strong>18</strong><span>secondary</span></div>
      <div class="metric"><strong>7</strong><span>warning</span></div>
    </div>
    <div class="spark"></div>
    <div class="warn">Attention state</div>
    <table>
      <thead><tr><th>Element</th><th>State</th></tr></thead>
      <tbody><tr><td>Sample row</td><td>Active</td></tr></tbody>
    </table>
  </section>
</main>
</body>
</html>
HTML;
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

                            <iframe
                                title="{{ __('Theme preview for :theme', ['theme' => $theme->name]) }}"
                                class="h-72 w-full overflow-hidden rounded-md border border-zinc-200 bg-white shadow-sm dark:border-zinc-700"
                                data-test="theme-preview"
                                sandbox
                                srcdoc="{{ $this->themePreviewHtml($theme) }}"></iframe>
                        </div>
                    </article>
                @endforeach
            </div>
        </x-data-table>
    @endif
</div>
