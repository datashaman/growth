<?php

use App\Models\Requirement;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public string $requirementId;

    public function mount(string $requirementId): void
    {
        $this->requirementId = $requirementId;
    }

    public function delete(): void
    {
        $requirement = Requirement::find($this->requirementId);

        abort_if($requirement === null, 404);

        $requirement->delete();

        $this->redirectRoute('capabilities', navigate: true);
    }
}; ?>

<flux:modal name="delete-requirement" :show="$errors->isNotEmpty()" focusable class="max-w-md">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete this requirement?') }}</flux:heading>
            <flux:subheading>{{ __('Children, work-item links, and test-case links will be detached.') }}</flux:subheading>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="danger">{{ __('Delete requirement') }}</flux:button>
        </div>
    </form>
</flux:modal>
