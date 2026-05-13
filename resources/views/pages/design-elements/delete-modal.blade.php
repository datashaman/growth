<?php

use App\Models\DesignElement;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $designElementId = null;

    public string $name = '';

    #[On('delete-design-element')]
    public function load(string $designElementId): void
    {
        $element = DesignElement::find($designElementId);

        abort_if($element === null, 404);

        $this->designElementId = $designElementId;
        $this->name = $element->name;

        $this->modal('delete-design-element')->show();
    }

    public function delete(): void
    {
        $element = DesignElement::find($this->designElementId);

        abort_if($element === null, 404);

        $element->delete();

        $this->modal('delete-design-element')->close();
        $this->reset(['designElementId', 'name']);
        $this->dispatch('design-element-saved');
    }
}; ?>

<flux:modal name="delete-design-element" :show="$errors->isNotEmpty()" focusable class="max-w-md">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete this element?') }}</flux:heading>
            @if ($name)
                <flux:subheading>{{ __('“:name” will be removed.', ['name' => $name]) }}</flux:subheading>
            @endif
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="danger">{{ __('Delete element') }}</flux:button>
        </div>
    </form>
</flux:modal>
