<?php

use App\Models\Milestone;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $milestoneId = null;

    public string $name = '';

    #[On('delete-milestone')]
    public function load(string $milestoneId): void
    {
        $milestone = Milestone::find($milestoneId);

        abort_if($milestone === null, 404);

        $this->milestoneId = $milestoneId;
        $this->name = $milestone->name;

        $this->modal('delete-milestone')->show();
    }

    public function delete(): void
    {
        $milestone = Milestone::find($this->milestoneId);

        abort_if($milestone === null, 404);

        $milestone->delete();

        $this->modal('delete-milestone')->close();
        $this->reset(['milestoneId', 'name']);
        $this->dispatch('milestone-saved');
    }
}; ?>

<flux:modal name="delete-milestone" :show="$errors->isNotEmpty()" focusable class="max-w-md">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete this milestone?') }}</flux:heading>
            @if ($name)
                <flux:subheading>{{ __('“:name” will be removed.', ['name' => $name]) }}</flux:subheading>
            @endif
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="danger">{{ __('Delete milestone') }}</flux:button>
        </div>
    </form>
</flux:modal>
