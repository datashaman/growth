<?php

use App\Models\Project;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $projectId = null;

    public string $text = '';
    public ?string $raised_by_stakeholder_id = null;
    public string $viewpoint_hints_text = '';

    public function mount(?string $projectId = null): void
    {
        $this->projectId = $projectId;
    }

    #[Computed]
    public function project(): ?Project
    {
        return $this->projectId ? Project::find($this->projectId) : null;
    }

    #[Computed]
    public function stakeholderOptions()
    {
        return $this->project?->stakeholders()->orderBy('name')->get(['id', 'name']) ?? collect();
    }

    public function save(): void
    {
        $project = $this->project;

        abort_if($project === null, 404);

        $data = $this->validate([
            'text' => ['required', 'string'],
            'raised_by_stakeholder_id' => [
                'nullable',
                Rule::exists('stakeholders', 'id')->where('project_id', $project->id),
            ],
            'viewpoint_hints_text' => ['nullable', 'string'],
        ]);

        $project->concerns()->create([
            'text' => $data['text'],
            'raised_by_stakeholder_id' => $data['raised_by_stakeholder_id'] ?: null,
            'viewpoint_hints' => $this->splitHints($data['viewpoint_hints_text'] ?? ''),
        ]);

        $this->reset(['text', 'raised_by_stakeholder_id', 'viewpoint_hints_text']);
        $this->modal('create-concern')->close();
        $this->dispatch('concern-saved');
    }

    /**
     * @return array<int, string>|null
     */
    private function splitHints(string $text): ?array
    {
        $hints = collect(preg_split('/[,\r\n]+/', $text))
            ->map(fn (string $hint): string => trim($hint))
            ->filter()
            ->values()
            ->all();

        return $hints === [] ? null : $hints;
    }
}; ?>

<flux:modal name="create-concern" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('New concern') }}</flux:heading>
            <flux:subheading>{{ __('Something a stakeholder needs the system to address.') }}</flux:subheading>
        </div>

        <flux:textarea wire:model="text" :label="__('Text')" rows="3" required />

        <flux:select wire:model="raised_by_stakeholder_id" :label="__('Raised by')">
            <flux:select.option value="">{{ __('— None —') }}</flux:select.option>
            @foreach ($this->stakeholderOptions as $stakeholder)
                <flux:select.option value="{{ $stakeholder->id }}">{{ $stakeholder->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="viewpoint_hints_text" :label="__('Viewpoint hints')" :placeholder="__('Comma-separated')" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Create concern') }}</flux:button>
        </div>
    </form>
</flux:modal>
