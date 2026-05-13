<?php

use App\Models\TestPlan;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $testPlanId = null;

    public string $name = '';
    public string $level = 'system';
    public string $scope = '';
    public string $approach = '';
    public string $pass_fail_criteria = '';

    #[On('edit-test-plan')]
    public function load(string $testPlanId): void
    {
        $plan = TestPlan::find($testPlanId);

        abort_if($plan === null, 404);

        $this->testPlanId = $testPlanId;
        $this->name = $plan->name;
        $this->level = $plan->level;
        $this->scope = (string) $plan->scope;
        $this->approach = (string) $plan->approach;
        $this->pass_fail_criteria = (string) $plan->pass_fail_criteria;

        $this->modal('edit-test-plan')->show();
    }

    #[Computed]
    public function testPlan(): ?TestPlan
    {
        return $this->testPlanId ? TestPlan::find($this->testPlanId) : null;
    }

    public function save(): void
    {
        $plan = $this->testPlan;

        abort_if($plan === null, 404);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', Rule::in(TestPlan::LEVELS)],
            'scope' => ['nullable', 'string'],
            'approach' => ['nullable', 'string'],
            'pass_fail_criteria' => ['nullable', 'string'],
        ]);

        $plan->update([
            'name' => $data['name'],
            'level' => $data['level'],
            'scope' => $data['scope'] ?: null,
            'approach' => $data['approach'] ?: null,
            'pass_fail_criteria' => $data['pass_fail_criteria'] ?: null,
        ]);

        $this->modal('edit-test-plan')->close();
        $this->dispatch('test-plan-saved');
    }
}; ?>

<flux:modal name="edit-test-plan" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Edit test plan') }}</flux:heading>
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
            <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
        </div>
    </form>
</flux:modal>
