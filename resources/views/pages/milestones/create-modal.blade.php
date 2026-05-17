<?php

use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $projectId = null;

    public string $name = '';
    public string $exit_criteria = '';
    public string $status = 'pending';

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
            'exit_criteria' => ['nullable', 'string'],
            'status' => ['required', Rule::in(Milestone::STATUSES)],
        ]);

        $project->milestones()->create([
            ...$data,
            'exit_criteria' => $data['exit_criteria'] ?: null,
        ]);

        $this->reset(['name', 'exit_criteria']);
        $this->modal('create-milestone')->close();
        $this->dispatch('milestone-saved');
    }
}; ?>

<flux:modal name="create-milestone" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('New milestone') }}</flux:heading>
        </div>

        <flux:input wire:model="name" :label="__('Name')" required />

        <flux:select wire:model="status" :label="__('Status')">
            @foreach (\App\Models\Milestone::STATUSES as $option)
                <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:textarea wire:model="exit_criteria" :label="__('Exit criteria')" rows="3" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Create milestone') }}</flux:button>
        </div>
    </form>
</flux:modal>
