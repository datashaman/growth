<?php

use App\Models\Project;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $projectId = null;

    public string $name = '';
    public string $role = '';
    public string $kind = 'individual';
    public string $description = '';

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
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:100'],
            'kind' => ['required', Rule::in(['individual', 'class'])],
            'description' => ['nullable', 'string'],
        ]);

        $project->stakeholders()->create([
            'name' => $data['name'],
            'role' => $data['role'] ?: null,
            'kind' => $data['kind'],
            'description' => $data['description'] ?: null,
        ]);

        $this->reset(['name', 'role', 'description']);
        $this->modal('create-stakeholder')->close();
        $this->dispatch('stakeholder-saved');
    }
}; ?>

<flux:modal name="create-stakeholder" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('New stakeholder') }}</flux:heading>
            <flux:subheading>{{ __('A person or class of people whose concerns this project must address.') }}</flux:subheading>
        </div>

        <flux:input wire:model="name" :label="__('Name')" required />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:input wire:model="role" :label="__('Role')" :placeholder="__('e.g. Sponsor, End user')" />
            <flux:select wire:model="kind" :label="__('Kind')">
                <flux:select.option value="individual">{{ __('Individual') }}</flux:select.option>
                <flux:select.option value="class">{{ __('Class / group') }}</flux:select.option>
            </flux:select>
        </div>

        <flux:textarea wire:model="description" :label="__('Description')" rows="3" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Create stakeholder') }}</flux:button>
        </div>
    </form>
</flux:modal>
