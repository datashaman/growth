<?php

use App\Models\Project;
use App\Models\TestPlan;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $projectId = null;

    public string $name = '';
    public string $level = 'system';
    public string $scope = '';
    public string $approach = '';
    public string $pass_fail_criteria = '';

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
            'level' => ['required', Rule::in(TestPlan::LEVELS)],
            'scope' => ['nullable', 'string'],
            'approach' => ['nullable', 'string'],
            'pass_fail_criteria' => ['nullable', 'string'],
        ]);

        $project->testPlans()->create([
            'name' => $data['name'],
            'level' => $data['level'],
            'scope' => $data['scope'] ?: null,
            'approach' => $data['approach'] ?: null,
            'pass_fail_criteria' => $data['pass_fail_criteria'] ?: null,
        ]);

        $this->reset(['name', 'scope', 'approach', 'pass_fail_criteria']);
        $this->modal('create-test-plan')->close();
        $this->dispatch('test-plan-saved');
    }
}; ?>

<flux:modal name="create-test-plan" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('New test plan') }}</flux:heading>
        </div>

        <flux:input wire:model="name" :label="__('Name')" required />

        <flux:select wire:model="level" :label="__('Level')">
            @foreach (\App\Models\TestPlan::LEVELS as $option)
                <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:textarea wire:model="scope" :label="__('Scope')" rows="2" />
        <flux:textarea wire:model="approach" :label="__('Approach')" rows="2" />
        <flux:textarea wire:model="pass_fail_criteria" :label="__('Pass / fail criteria')" rows="2" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Create plan') }}</flux:button>
        </div>
    </form>
</flux:modal>
