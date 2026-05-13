<?php

use App\Models\Stakeholder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $stakeholderId = null;

    public string $name = '';
    public string $role = '';
    public string $kind = 'individual';
    public string $description = '';

    #[On('edit-stakeholder')]
    public function load(string $stakeholderId): void
    {
        $stakeholder = Stakeholder::find($stakeholderId);

        abort_if($stakeholder === null, 404);

        $this->stakeholderId = $stakeholderId;
        $this->name = $stakeholder->name;
        $this->role = (string) $stakeholder->role;
        $this->kind = $stakeholder->kind;
        $this->description = (string) $stakeholder->description;

        $this->modal('edit-stakeholder')->show();
    }

    #[Computed]
    public function stakeholder(): ?Stakeholder
    {
        return $this->stakeholderId ? Stakeholder::find($this->stakeholderId) : null;
    }

    public function save(): void
    {
        $stakeholder = $this->stakeholder;

        abort_if($stakeholder === null, 404);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:100'],
            'kind' => ['required', Rule::in(['individual', 'class'])],
            'description' => ['nullable', 'string'],
        ]);

        $stakeholder->update([
            'name' => $data['name'],
            'role' => $data['role'] ?: null,
            'kind' => $data['kind'],
            'description' => $data['description'] ?: null,
        ]);

        $this->modal('edit-stakeholder')->close();
        $this->dispatch('stakeholder-saved');
    }
}; ?>

<flux:modal name="edit-stakeholder" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Edit stakeholder') }}</flux:heading>
        </div>

        <flux:input wire:model="name" :label="__('Name')" required />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:input wire:model="role" :label="__('Role')" />
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
            <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
        </div>
    </form>
</flux:modal>
