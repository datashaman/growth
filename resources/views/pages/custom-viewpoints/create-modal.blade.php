<?php

use App\Models\CustomViewpoint;
use App\Models\DesignView;
use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $projectId = null;

    public string $name = '';
    public string $concerns = '';
    public string $element_types = '';
    public string $languages = '';
    public string $source = '';

    public function mount(?string $projectId = null): void
    {
        $this->projectId = $projectId;
    }

    #[Computed]
    public function project(): ?Project
    {
        return $this->projectId ? Project::find($this->projectId) : null;
    }

    public function save(): void
    {
        $project = $this->project;

        abort_if($project === null, 404);

        $data = $this->validate([
            'name' => [
                'required', 'string', 'max:100',
                'not_in:'.implode(',', DesignView::BUILTIN_VIEWPOINTS),
                function (string $attribute, mixed $value, callable $fail) use ($project) {
                    if (CustomViewpoint::query()->where('project_id', $project->id)->where('name', $value)->exists()) {
                        $fail(__('A custom viewpoint with this name already exists in this project.'));
                    }
                },
            ],
            'concerns' => ['required', 'string'],
            'element_types' => ['required', 'string'],
            'languages' => ['required', 'string'],
            'source' => ['nullable', 'string', 'max:255'],
        ]);

        $concerns = $this->splitCsv($data['concerns']);
        $elementTypes = $this->splitCsv($data['element_types']);
        $languages = $this->splitCsv($data['languages']);

        if ($concerns === []) {
            $this->addError('concerns', __('Add at least one concern.'));

            return;
        }
        if ($elementTypes === []) {
            $this->addError('element_types', __('Add at least one element type.'));

            return;
        }
        if ($languages === []) {
            $this->addError('languages', __('Add at least one language.'));

            return;
        }

        $project->customViewpoints()->create([
            'name' => $data['name'],
            'concerns' => $concerns,
            'element_types' => $elementTypes,
            'languages' => $languages,
            'source' => $data['source'] ?: null,
        ]);

        $this->reset(['name', 'concerns', 'element_types', 'languages', 'source']);
        $this->modal('create-custom-viewpoint')->close();
        $this->dispatch('custom-viewpoint-saved');
    }

    /**
     * @return array<int,string>
     */
    private function splitCsv(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn ($v) => trim($v))
            ->filter()
            ->values()
            ->all();
    }
}; ?>

<flux:modal name="create-custom-viewpoint" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('New custom viewpoint') }}</flux:heading>
            <flux:subheading>{{ __('Defines coverage rules for design views beyond the 12 built-in viewpoints.') }}</flux:subheading>
        </div>

        <flux:input wire:model="name" :label="__('Name')" required :description="__('Must not collide with a built-in viewpoint name.')" />

        <flux:input wire:model="concerns" :label="__('Concerns')" required :placeholder="__('comma-separated, e.g. security, performance')" />
        <flux:input wire:model="element_types" :label="__('Element types')" required :placeholder="__('comma-separated, e.g. service, channel')" />
        <flux:input wire:model="languages" :label="__('Languages')" required :placeholder="__('comma-separated, e.g. C4, ArchiMate')" />

        <flux:input wire:model="source" :label="__('Source')" :placeholder="__('citation or authorship')" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Create viewpoint') }}</flux:button>
        </div>
    </form>
</flux:modal>
