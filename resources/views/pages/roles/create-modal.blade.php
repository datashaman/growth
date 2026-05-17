<?php

use App\Models\Project;
use App\Models\Role;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $projectId = null;

    public string $name = '';
    public string $responsibilities = '';

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
                'required', 'string', 'max:255',
                function (string $attribute, mixed $value, callable $fail) use ($project) {
                    if (Role::query()->where('project_id', $project->id)->where('name', $value)->exists()) {
                        $fail(__('A role with this name already exists in this project.'));
                    }
                },
            ],
            'responsibilities' => ['nullable', 'string'],
        ]);

        $project->roles()->create([
            'name' => $data['name'],
            'responsibilities' => $data['responsibilities'] ?: null,
        ]);

        $this->reset(['name', 'responsibilities']);
        $this->modal('create-role')->close();
        $this->dispatch('role-saved');
    }
}; ?>

<flux:modal name="create-role" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('New role') }}</flux:heading>
            <flux:subheading>{{ __('Define a role that work items, risks, and reviews can be assigned to.') }}</flux:subheading>
        </div>

        <flux:input wire:model="name" :label="__('Name')" required />

        <flux:textarea wire:model="responsibilities" :label="__('Responsibilities')" rows="3" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Create role') }}</flux:button>
        </div>
    </form>
</flux:modal>
