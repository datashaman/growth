<?php

use App\Models\Theme;
use App\Support\ThemePreviewSpecimen;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Theme')] class extends Component {
    public Theme $theme;

    /**
     * @var array<string,string>
     */
    public array $tokens = [];

    public string $compiledCss = '';

    public function mount(Theme $theme): void
    {
        abort_unless(
            $theme->project->workspace_id === auth()->user()?->active_workspace_id,
            404,
        );

        $this->theme = $theme->load('project');
        $this->tokens = $this->theme->normalizedCssTokens();
        $this->compiledCss = $this->theme->cssForInjection();
    }

    public function themePreviewHtml(): string
    {
        return ThemePreviewSpecimen::html($this->theme);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-detail-page-header
        :title="$theme->name"
        back-route="themes"
        :back-label="__('Back to themes')">
        <x-slot:badges>
            <flux:badge color="zinc" size="sm" class="font-mono">{{ $theme->slug }}</flux:badge>
            @if ($theme->is_default)
                <flux:badge color="sky" size="sm">{{ __('default') }}</flux:badge>
            @endif
        </x-slot:badges>

        <x-slot:description>
            {{ __('Theme in project') }}
            <a href="{{ route('dashboard', ['project' => $theme->project_id]) }}" wire:navigate class="underline">{{ $theme->project->name }}</a>
        </x-slot:description>
    </x-detail-page-header>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_28rem]">
        <div class="min-w-0 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <flux:heading size="lg">{{ __('Preview specimen') }}</flux:heading>
                <div class="flex flex-wrap gap-2">
                    <flux:badge color="zinc" size="sm">{{ count($tokens) }} {{ __('tokens') }}</flux:badge>
                    <flux:badge :color="filled($theme->raw_css) ? 'green' : 'zinc'" size="sm">{{ filled($theme->raw_css) ? __('CSS') : __('no CSS') }}</flux:badge>
                </div>
            </div>

            <iframe
                title="{{ __('Theme preview for :theme', ['theme' => $theme->name]) }}"
                class="h-[32rem] w-full overflow-hidden rounded-md border border-zinc-200 bg-white shadow-sm dark:border-zinc-700"
                data-test="theme-detail-preview"
                sandbox
                srcdoc="{{ $this->themePreviewHtml() }}"></iframe>
        </div>

        <aside class="flex min-w-0 flex-col gap-4">
            <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg" class="mb-3">{{ __('Design notes') }}</flux:heading>
                @if ($theme->description || $theme->design_notes)
                    <div class="space-y-3 text-sm text-zinc-700 dark:text-zinc-300">
                        @if ($theme->description)
                            <p>{{ $theme->description }}</p>
                        @endif
                        @if ($theme->design_notes)
                            <p>{{ $theme->design_notes }}</p>
                        @endif
                    </div>
                @else
                    <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('No design notes captured yet.') }}</flux:text>
                @endif
            </section>

            <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg" class="mb-3">{{ __('Tokens') }}</flux:heading>
                @if ($tokens === [])
                    <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('No CSS tokens captured.') }}</flux:text>
                @else
                    <dl class="grid gap-2">
                        @foreach ($tokens as $name => $value)
                            <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950/40">
                                <dt class="min-w-0 truncate font-mono text-xs text-zinc-600 dark:text-zinc-300">--{{ $name }}</dt>
                                <dd class="flex min-w-0 items-center gap-2">
                                    <span class="size-4 rounded border border-zinc-300 dark:border-zinc-600" style="background: {{ $value }}"></span>
                                    <code class="max-w-32 truncate text-xs text-zinc-700 dark:text-zinc-300">{{ $value }}</code>
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </section>
        </aside>
    </section>

    <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg" class="mb-3">{{ __('Compiled CSS') }}</flux:heading>
        @if (trim($compiledCss) === '')
            <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('This theme does not compile any CSS yet.') }}</flux:text>
        @else
            <pre class="overflow-x-auto rounded-md border border-zinc-200 bg-zinc-950 p-4 text-xs leading-5 text-zinc-100 dark:border-zinc-700"><code>{{ $compiledCss }}</code></pre>
        @endif
    </section>
</div>
