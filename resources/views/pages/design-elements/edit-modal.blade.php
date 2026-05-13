<?php

use App\Models\DesignElement;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public const KINDS = ['entity', 'relationship', 'attribute', 'constraint'];

    #[Locked]
    public ?string $designElementId = null;

    public string $name = '';
    public string $kind = 'entity';
    public string $type = '';
    public string $purpose = '';

    #[On('edit-design-element')]
    public function load(string $designElementId): void
    {
        $element = DesignElement::find($designElementId);

        abort_if($element === null, 404);

        $this->designElementId = $designElementId;
        $this->name = $element->name;
        $this->kind = $element->kind;
        $this->type = (string) $element->type;
        $this->purpose = (string) $element->purpose;

        $this->modal('edit-design-element')->show();
    }

    #[Computed]
    public function designElement(): ?DesignElement
    {
        return $this->designElementId ? DesignElement::find($this->designElementId) : null;
    }

    public function save(): void
    {
        $element = $this->designElement;

        abort_if($element === null, 404);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in(self::KINDS)],
            'type' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string'],
        ]);

        $element->update([
            'name' => $data['name'],
            'kind' => $data['kind'],
            'type' => $data['type'] ?: null,
            'purpose' => $data['purpose'] ?: null,
        ]);

        $this->modal('edit-design-element')->close();
        $this->dispatch('design-element-saved');
    }
}; ?>

<flux:modal name="edit-design-element" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Edit element') }}</flux:heading>
        </div>

        <flux:input wire:model="name" :label="__('Name')" required />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:select wire:model="kind" :label="__('Kind')">
                @foreach (['entity', 'relationship', 'attribute', 'constraint'] as $option)
                    <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="type" :label="__('Type')" />
        </div>

        <flux:textarea wire:model="purpose" :label="__('Purpose')" rows="3" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
        </div>
    </form>
</flux:modal>
