<?php

use App\Growth\Transitions\DeferMilestone;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\Milestone;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $milestoneId = null;

    public string $name = '';

    public string $newTargetDate = '';

    #[On('defer-milestone')]
    public function load(string $milestoneId): void
    {
        $milestone = Milestone::find($milestoneId);

        abort_if($milestone === null, 404);

        $this->milestoneId = $milestoneId;
        $this->name = $milestone->name;
        $this->newTargetDate = $milestone->target_date?->format('Y-m-d') ?? '';

        $this->modal('defer-milestone')->show();
    }

    public function defer(): void
    {
        $data = $this->validate([
            'newTargetDate' => ['required', 'date'],
        ]);

        $milestone = Milestone::find($this->milestoneId);

        abort_if($milestone === null, 404);

        try {
            (new DeferMilestone($data['newTargetDate']))->apply($milestone, auth()->user());
        } catch (IllegalTransitionException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
            $this->modal('defer-milestone')->close();

            return;
        }

        $this->modal('defer-milestone')->close();
        $this->reset(['milestoneId', 'name', 'newTargetDate']);
        $this->dispatch('milestone-saved');

        Flux::toast(variant: 'success', text: __('Milestone deferred.'));
    }
}; ?>

<flux:modal name="defer-milestone" :show="$errors->isNotEmpty()" focusable class="max-w-md">
    <form wire:submit="defer" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Defer this milestone?') }}</flux:heading>
            @if ($name)
                <flux:subheading>{{ __('“:name” moves to the deferred status with a new target date.', ['name' => $name]) }}</flux:subheading>
            @endif
        </div>

        <flux:input wire:model="newTargetDate" type="date" :label="__('New target date')" required />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Defer milestone') }}</flux:button>
        </div>
    </form>
</flux:modal>
