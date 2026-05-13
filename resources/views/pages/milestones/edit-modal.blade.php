<?php

use App\Models\Milestone;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $milestoneId = null;

    public string $name = '';
    public string $target_date = '';
    public string $exit_criteria = '';
    public string $status = 'pending';

    #[On('edit-milestone')]
    public function load(string $milestoneId): void
    {
        $this->milestoneId = $milestoneId;
        $milestone = Milestone::find($milestoneId);

        abort_if($milestone === null, 404);

        $this->name = $milestone->name;
        $this->target_date = $milestone->target_date?->format('Y-m-d') ?? '';
        $this->exit_criteria = (string) $milestone->exit_criteria;
        $this->status = $milestone->status;

        $this->modal('edit-milestone')->show();
    }

    #[Computed]
    public function milestone(): ?Milestone
    {
        return $this->milestoneId ? Milestone::find($this->milestoneId) : null;
    }

    public function save(): void
    {
        $milestone = $this->milestone;

        abort_if($milestone === null, 404);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'target_date' => ['nullable', 'date'],
            'exit_criteria' => ['nullable', 'string'],
            'status' => ['required', Rule::in(Milestone::STATUSES)],
        ]);

        $milestone->update([
            ...$data,
            'target_date' => $data['target_date'] ?: null,
            'exit_criteria' => $data['exit_criteria'] ?: null,
        ]);

        $this->modal('edit-milestone')->close();
        $this->dispatch('milestone-saved');
    }
}; ?>

<flux:modal name="edit-milestone" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Edit milestone') }}</flux:heading>
        </div>

        <flux:input wire:model="name" :label="__('Name')" required />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:input wire:model="target_date" type="date" :label="__('Target date')" />
            <flux:select wire:model="status" :label="__('Status')">
                @foreach (\App\Models\Milestone::STATUSES as $option)
                    <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:textarea wire:model="exit_criteria" :label="__('Exit criteria')" rows="3" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
        </div>
    </form>
</flux:modal>
