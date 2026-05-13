<?php

use App\Models\Requirement;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit requirement')] class extends Component {
    public Requirement $requirement;

    public string $doc = 'srs';
    public string $type = 'functional';
    public string $text = '';
    public string $rationale = '';
    public string $source = '';
    public ?string $priority = null;
    public ?string $parent_id = null;
    public string $acceptance_criteria_text = '';
    public string $tags_text = '';

    public function mount(Requirement $requirement): void
    {
        $this->requirement = $requirement;
        $this->doc = $requirement->doc;
        $this->type = $requirement->type;
        $this->text = $requirement->text;
        $this->rationale = (string) $requirement->rationale;
        $this->source = (string) $requirement->source;
        $this->priority = $requirement->priority;
        $this->parent_id = $requirement->parent_id;
        $this->acceptance_criteria_text = is_array($requirement->acceptance_criteria)
            ? implode("\n", $requirement->acceptance_criteria)
            : '';
        $this->tags_text = is_array($requirement->tags)
            ? implode(', ', $requirement->tags)
            : '';
    }

    #[Computed]
    public function parentOptions()
    {
        return $this->requirement->project->requirements()
            ->where('id', '!=', $this->requirement->id)
            ->orderBy('doc')
            ->orderBy('text')
            ->get(['id', 'doc', 'text']);
    }

    public function save(): void
    {
        $data = $this->validate([
            'doc' => ['required', Rule::in(['strs', 'syrs', 'srs'])],
            'type' => ['required', Rule::in(['functional', 'performance', 'usability', 'interface', 'design_constraint', 'process', 'non_functional'])],
            'text' => ['required', 'string'],
            'rationale' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', Rule::in(['high', 'medium', 'low'])],
            'parent_id' => [
                'nullable',
                Rule::exists('requirements', 'id')->where('project_id', $this->requirement->project_id),
            ],
            'acceptance_criteria_text' => ['nullable', 'string'],
            'tags_text' => ['nullable', 'string'],
        ]);

        $this->requirement->update([
            'doc' => $data['doc'],
            'type' => $data['type'],
            'text' => $data['text'],
            'rationale' => $data['rationale'] ?: null,
            'source' => $data['source'] ?: null,
            'priority' => $data['priority'] ?: null,
            'parent_id' => $data['parent_id'] ?: null,
            'acceptance_criteria' => $this->splitLines($data['acceptance_criteria_text'] ?? ''),
            'tags' => $this->splitTags($data['tags_text'] ?? ''),
        ]);

        $this->redirectRoute('requirements.show', ['requirement' => $this->requirement->id], navigate: true);
    }

    /**
     * @return array<int, string>|null
     */
    private function splitLines(string $text): ?array
    {
        $lines = collect(preg_split('/\r?\n/', $text))
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();

        return $lines === [] ? null : $lines;
    }

    /**
     * @return array<int, string>|null
     */
    private function splitTags(string $text): ?array
    {
        $tags = collect(preg_split('/[,\r\n]+/', $text))
            ->map(fn (string $tag): string => trim($tag))
            ->filter()
            ->values()
            ->all();

        return $tags === [] ? null : $tags;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-detail-page-header
        :title="__('Edit requirement')"
        :back-href="route('requirements.show', $requirement)"
        :back-label="__('Cancel and return to requirement')">
        <x-slot:description>
            {{ __('In project') }} <a href="{{ route('dashboard', ['project' => $requirement->project_id]) }}" class="underline">{{ $requirement->project->name }}</a>
        </x-slot:description>
    </x-detail-page-header>

    <form wire:submit="save" class="space-y-6">
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-3">{{ __('Statement') }}</flux:heading>
            <flux:textarea wire:model="text" :label="__('Text')" rows="4" required />
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <flux:select wire:model="doc" :label="__('Doc')">
                    <flux:select.option value="strs">STRS — {{ __('Stakeholder requirements') }}</flux:select.option>
                    <flux:select.option value="syrs">SyRS — {{ __('System requirements') }}</flux:select.option>
                    <flux:select.option value="srs">SRS — {{ __('Software requirements') }}</flux:select.option>
                </flux:select>
                <flux:select wire:model="type" :label="__('Type')">
                    @foreach (['functional', 'performance', 'usability', 'interface', 'design_constraint', 'process', 'non_functional'] as $option)
                        <flux:select.option value="{{ $option }}">{{ str_replace('_', ' ', $option) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="priority" :label="__('Priority')">
                    <flux:select.option value="">{{ __('— None —') }}</flux:select.option>
                    @foreach (['high', 'medium', 'low'] as $option)
                        <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-3">{{ __('Context') }}</flux:heading>
            <div class="space-y-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input wire:model="source" :label="__('Source')" />
                    <flux:select wire:model="parent_id" :label="__('Parent requirement')">
                        <flux:select.option value="">{{ __('— None —') }}</flux:select.option>
                        @foreach ($this->parentOptions as $option)
                            <flux:select.option value="{{ $option->id }}">[{{ strtoupper($option->doc) }}] {{ \Illuminate\Support\Str::limit($option->text, 60) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:textarea wire:model="rationale" :label="__('Rationale')" rows="3" />
            </div>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-3">{{ __('Acceptance criteria') }}</flux:heading>
            <flux:textarea wire:model="acceptance_criteria_text" :placeholder="__('One criterion per line')" rows="4" />
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-3">{{ __('Tags') }}</flux:heading>
            <flux:input wire:model="tags_text" :placeholder="__('Comma-separated')" />
        </section>

        <div class="flex justify-end gap-2">
            <flux:button :href="route('requirements.show', $requirement)" wire:navigate variant="filled">{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
        </div>
    </form>
</div>
