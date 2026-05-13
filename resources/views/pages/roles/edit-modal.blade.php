<?php

use App\Models\Role;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $roleId = null;

    public string $name = '';
    public string $responsibilities = '';
    public ?string $weekly_capacity_hours = null;
    public ?string $hourly_rate_amount = null;
    public string $rate_currency = '';

    #[On('edit-role')]
    public function load(string $roleId): void
    {
        $role = Role::find($roleId);

        abort_if($role === null, 404);

        $this->roleId = $roleId;
        $this->name = $role->name;
        $this->responsibilities = (string) $role->responsibilities;
        $this->weekly_capacity_hours = $role->weekly_capacity_hours !== null ? (string) $role->weekly_capacity_hours : null;
        $this->hourly_rate_amount = $role->hourly_rate_amount !== null ? (string) $role->hourly_rate_amount : null;
        $this->rate_currency = (string) $role->rate_currency;

        $this->modal('edit-role')->show();
    }

    #[Computed]
    public function role(): ?Role
    {
        return $this->roleId ? Role::find($this->roleId) : null;
    }

    public function save(): void
    {
        $role = $this->role;

        abort_if($role === null, 404);

        $data = $this->validate([
            'name' => [
                'required', 'string', 'max:255',
                function (string $attribute, mixed $value, callable $fail) use ($role) {
                    if (Role::query()
                        ->where('project_id', $role->project_id)
                        ->where('id', '!=', $role->id)
                        ->where('name', $value)
                        ->exists()
                    ) {
                        $fail(__('A role with this name already exists in this project.'));
                    }
                },
            ],
            'responsibilities' => ['nullable', 'string'],
            'weekly_capacity_hours' => ['nullable', 'numeric', 'min:0'],
            'hourly_rate_amount' => ['nullable', 'numeric', 'min:0'],
            'rate_currency' => ['nullable', 'string', 'max:8'],
        ]);

        $role->update([
            'name' => $data['name'],
            'responsibilities' => $data['responsibilities'] ?: null,
            'weekly_capacity_hours' => $data['weekly_capacity_hours'] !== null && $data['weekly_capacity_hours'] !== '' ? $data['weekly_capacity_hours'] : null,
            'hourly_rate_amount' => $data['hourly_rate_amount'] !== null && $data['hourly_rate_amount'] !== '' ? $data['hourly_rate_amount'] : null,
            'rate_currency' => $data['rate_currency'] ?: null,
        ]);

        $this->modal('edit-role')->close();
        $this->dispatch('role-saved');
    }
}; ?>

<flux:modal name="edit-role" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Edit role') }}</flux:heading>
        </div>

        <flux:input wire:model="name" :label="__('Name')" required />
        <flux:textarea wire:model="responsibilities" :label="__('Responsibilities')" rows="3" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <flux:input wire:model="weekly_capacity_hours" type="number" step="0.5" min="0" :label="__('Weekly capacity (h)')" />
            <flux:input wire:model="hourly_rate_amount" type="number" step="0.01" min="0" :label="__('Hourly rate')" />
            <flux:input wire:model="rate_currency" :label="__('Currency')" />
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
        </div>
    </form>
</flux:modal>
