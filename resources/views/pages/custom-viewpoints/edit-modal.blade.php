<?php

use App\Models\CustomViewpoint;
use App\Models\DesignView;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $customViewpointId = null;

    public string $name = '';
    public string $concerns = '';
    public string $element_types = '';
    public string $languages = '';
    public string $source = '';

    #[On('edit-custom-viewpoint')]
    public function load(string $customViewpointId): void
    {
        $viewpoint = CustomViewpoint::find($customViewpointId);

        abort_if($viewpoint === null, 404);

        $this->customViewpointId = $customViewpointId;
        $this->name = $viewpoint->name;
        $this->concerns = implode(', ', $viewpoint->concerns ?? []);
        $this->element_types = implode(', ', $viewpoint->element_types ?? []);
        $this->languages = implode(', ', $viewpoint->languages ?? []);
        $this->source = (string) $viewpoint->source;

        $this->modal('edit-custom-viewpoint')->show();
    }

    public function save(): void
    {
        $viewpoint = CustomViewpoint::find($this->customViewpointId);

        abort_if($viewpoint === null, 404);

        $data = $this->validate([
            'name' => [
                'required', 'string', 'max:100',
                'not_in:'.implode(',', DesignView::BUILTIN_VIEWPOINTS),
                function (string $attribute, mixed $value, callable $fail) use ($viewpoint) {
                    $exists = CustomViewpoint::query()
                        ->where('project_id', $viewpoint->project_id)
                        ->where('name', $value)
                        ->where('id', '!=', $viewpoint->id)
                        ->exists();
                    if ($exists) {
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

        $viewpoint->update([
            'name' => $data['name'],
            'concerns' => $concerns,
            'element_types' => $elementTypes,
            'languages' => $languages,
            'source' => $data['source'] ?: null,
        ]);

        $this->modal('edit-custom-viewpoint')->close();
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

<flux:modal name="edit-custom-viewpoint" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Edit custom viewpoint') }}</flux:heading>
        </div>

        <flux:input wire:model="name" :label="__('Name')" required />

        <flux:input wire:model="concerns" :label="__('Concerns')" required :placeholder="__('comma-separated')" />
        <flux:input wire:model="element_types" :label="__('Element types')" required :placeholder="__('comma-separated')" />
        <flux:input wire:model="languages" :label="__('Languages')" required :placeholder="__('comma-separated')" />

        <flux:input wire:model="source" :label="__('Source')" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
        </div>
    </form>
</flux:modal>
