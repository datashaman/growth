<?php

use App\Models\Risk;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public string $riskId;

    public function mount(string $riskId): void
    {
        $this->riskId = $riskId;
    }

    public function delete(): void
    {
        $risk = Risk::find($this->riskId);

        abort_if($risk === null, 404);

        $risk->delete();

        $this->redirectRoute('dashboard', navigate: true);
    }
}; ?>

<flux:modal name="delete-risk" :show="$errors->isNotEmpty()" focusable class="max-w-md">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete this risk?') }}</flux:heading>
            <flux:subheading>{{ __('This action cannot be undone. The risk and its citations will be removed.') }}</flux:subheading>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="danger">{{ __('Delete risk') }}</flux:button>
        </div>
    </form>
</flux:modal>
