<?php

use App\Models\Anomaly;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public string $anomalyId;

    public function mount(string $anomalyId): void
    {
        $this->anomalyId = $anomalyId;
    }

    public function delete(): void
    {
        $anomaly = Anomaly::find($this->anomalyId);

        abort_if($anomaly === null, 404);

        $anomaly->delete();

        $this->redirectRoute('verification', navigate: true);
    }
}; ?>

<flux:modal name="delete-anomaly" :show="$errors->isNotEmpty()" focusable class="max-w-md">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete this anomaly?') }}</flux:heading>
            <flux:subheading>{{ __('This action cannot be undone.') }}</flux:subheading>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="danger">{{ __('Delete anomaly') }}</flux:button>
        </div>
    </form>
</flux:modal>
