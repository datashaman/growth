<?php

use App\Models\DesignView;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public const KINDS = ['entity', 'relationship', 'attribute', 'constraint'];

    #[Locked]
    public ?string $designViewId = null;

    public string $name = '';
    public string $kind = 'entity';
    public string $type = '';
    public string $purpose = '';

    #[On('create-design-element')]
    public function load(string $designViewId): void
    {
        $view = DesignView::find($designViewId);

        abort_if($view === null, 404);

        $this->designViewId = $designViewId;
        $this->reset(['name', 'type', 'purpose']);
        $this->kind = 'entity';

        $this->modal('create-design-element')->show();
    }

    #[Computed]
    public function designView(): ?DesignView
    {
        return $this->designViewId ? DesignView::find($this->designViewId) : null;
    }

    public function save(): void
    {
        $view = $this->designView;

        abort_if($view === null, 404);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in(self::KINDS)],
            'type' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string'],
        ]);

        $view->elements()->create([
            'name' => $data['name'],
            'kind' => $data['kind'],
            'type' => $data['type'] ?: null,
            'purpose' => $data['purpose'] ?: null,
        ]);

        $this->modal('create-design-element')->close();
        $this->dispatch('design-element-saved');
    }
}; ?>

<flux:modal name="create-design-element" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('New element') }}</flux:heading>
            @if ($this->designView)
                <flux:subheading>{{ __('In view ":name"', ['name' => $this->designView->name]) }}</flux:subheading>
            @endif
        </div>

        <flux:input wire:model="name" :label="__('Name')" required />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:select wire:model="kind" :label="__('Kind')">
                @foreach (['entity', 'relationship', 'attribute', 'constraint'] as $option)
                    <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="type" :label="__('Type')" :placeholder="__('Optional type tag')" />
        </div>

        <flux:textarea wire:model="purpose" :label="__('Purpose')" rows="3" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Add element') }}</flux:button>
        </div>
    </form>
</flux:modal>
