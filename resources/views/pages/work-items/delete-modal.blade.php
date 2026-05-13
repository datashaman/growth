<?php

use App\Models\WorkItem;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public string $workItemId;

    public function mount(string $workItemId): void
    {
        $this->workItemId = $workItemId;
    }

    public function delete(): void
    {
        $workItem = WorkItem::find($this->workItemId);

        abort_if($workItem === null, 404);

        $workItem->delete();

        $this->redirectRoute('plan', navigate: true);
    }
}; ?>

<flux:modal name="delete-work-item" :show="$errors->isNotEmpty()" focusable class="max-w-md">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete this work item?') }}</flux:heading>
            <flux:subheading>{{ __('Child items, requirement links, milestone links, and delivery links will be removed.') }}</flux:subheading>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="danger">{{ __('Delete work item') }}</flux:button>
        </div>
    </form>
</flux:modal>
