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
    public ?string $weekly_capacity_hours = null;
    public ?string $hourly_rate_amount = null;
    public string $rate_currency = '';

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
            'weekly_capacity_hours' => ['nullable', 'numeric', 'min:0'],
            'hourly_rate_amount' => ['nullable', 'numeric', 'min:0'],
            'rate_currency' => ['nullable', 'string', 'max:8'],
        ]);

        $project->roles()->create([
            'name' => $data['name'],
            'responsibilities' => $data['responsibilities'] ?: null,
            'weekly_capacity_hours' => $data['weekly_capacity_hours'] !== null && $data['weekly_capacity_hours'] !== '' ? $data['weekly_capacity_hours'] : null,
            'hourly_rate_amount' => $data['hourly_rate_amount'] !== null && $data['hourly_rate_amount'] !== '' ? $data['hourly_rate_amount'] : null,
            'rate_currency' => $data['rate_currency'] ?: null,
        ]);

        $this->reset(['name', 'responsibilities', 'weekly_capacity_hours', 'hourly_rate_amount', 'rate_currency']);
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

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <flux:input wire:model="weekly_capacity_hours" type="number" step="0.5" min="0" :label="__('Weekly capacity (h)')" />
            <flux:input wire:model="hourly_rate_amount" type="number" step="0.01" min="0" :label="__('Hourly rate')" />
            <flux:input wire:model="rate_currency" :label="__('Currency')" :placeholder="__('e.g. USD')" />
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Create role') }}</flux:button>
        </div>
    </form>
</flux:modal>
