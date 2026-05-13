<?php

use App\Models\TestPlan;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $testPlanId = null;

    public string $name = '';
    public int $caseCount = 0;

    #[On('delete-test-plan')]
    public function load(string $testPlanId): void
    {
        $plan = TestPlan::query()
            ->withCount('cases')
            ->find($testPlanId);

        abort_if($plan === null, 404);

        $this->testPlanId = $testPlanId;
        $this->name = $plan->name;
        $this->caseCount = (int) ($plan->cases_count ?? 0);

        $this->modal('delete-test-plan')->show();
    }

    public function delete(): void
    {
        $plan = TestPlan::find($this->testPlanId);

        abort_if($plan === null, 404);

        $plan->delete();

        $this->modal('delete-test-plan')->close();
        $this->reset(['testPlanId', 'name', 'caseCount']);
        $this->dispatch('test-plan-saved');
    }
}; ?>

<flux:modal name="delete-test-plan" :show="$errors->isNotEmpty()" focusable class="max-w-md">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete this test plan?') }}</flux:heading>
            @if ($name)
                <flux:subheading>{{ __('“:name” will be removed.', ['name' => $name]) }}</flux:subheading>
            @endif
            @if ($caseCount > 0)
                <flux:callout icon="exclamation-triangle" color="amber" class="mt-3">
                    <flux:callout.text>{{ __(':count test cases will cascade-delete with the plan.', ['count' => $caseCount]) }}</flux:callout.text>
                </flux:callout>
            @endif
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="danger">{{ __('Delete plan') }}</flux:button>
        </div>
    </form>
</flux:modal>
