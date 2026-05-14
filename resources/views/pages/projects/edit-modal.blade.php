<?php

use App\Models\Project;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $projectId = null;

    public string $name = '';
    public string $description = '';
    public int $rigor_level = 2;

    #[On('edit-project')]
    public function load(string $projectId): void
    {
        $project = Project::find($projectId);

        abort_if($project === null, 404);

        $this->projectId = $projectId;
        $this->name = $project->name;
        $this->description = (string) $project->description;
        $this->rigor_level = $project->rigor_level;

        $this->modal('edit-project')->show();
    }

    public function save(): void
    {
        $project = Project::find($this->projectId);

        abort_if($project === null, 404);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'rigor_level' => ['required', 'integer', 'between:1,4'],
        ]);

        $project->update([
            'name' => $data['name'],
            'description' => $data['description'] ?: null,
            'rigor_level' => $data['rigor_level'],
        ]);

        $this->modal('edit-project')->close();
        $this->dispatch('project-saved');
    }
}; ?>

<flux:modal name="edit-project" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Edit project') }}</flux:heading>
        </div>

        <flux:input wire:model="name" :label="__('Name')" required />

        <flux:textarea wire:model="description" :label="__('Description')" rows="3" />

        <flux:select wire:model="rigor_level" :label="__('Rigor level')" :description="__('1 = lowest rigor, 4 = highest. Drives readiness gates and review depth.')">
            <flux:select.option value="1">{{ __('1 — Minimal rigor') }}</flux:select.option>
            <flux:select.option value="2">{{ __('2 — Standard') }}</flux:select.option>
            <flux:select.option value="3">{{ __('3 — Elevated') }}</flux:select.option>
            <flux:select.option value="4">{{ __('4 — Safety-critical') }}</flux:select.option>
        </flux:select>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
        </div>
    </form>
</flux:modal>
