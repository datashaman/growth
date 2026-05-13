<?php

use App\Models\DesignView;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $designViewId = null;

    public string $name = '';
    public string $viewpoint = 'logical';
    public string $description = '';

    #[On('edit-design-view')]
    public function load(string $designViewId): void
    {
        $view = DesignView::find($designViewId);

        abort_if($view === null, 404);

        $this->designViewId = $designViewId;
        $this->name = $view->name;
        $this->viewpoint = $view->viewpoint;
        $this->description = (string) $view->description;

        $this->modal('edit-design-view')->show();
    }

    #[Computed]
    public function designView(): ?DesignView
    {
        return $this->designViewId ? DesignView::find($this->designViewId) : null;
    }

    #[Computed]
    public function customViewpointOptions()
    {
        return $this->designView?->project->customViewpoints()->orderBy('name')->get(['id', 'name']) ?? collect();
    }

    public function save(): void
    {
        $view = $this->designView;

        abort_if($view === null, 404);

        $allowed = array_merge(
            DesignView::BUILTIN_VIEWPOINTS,
            $this->customViewpointOptions->pluck('name')->all(),
        );

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'viewpoint' => ['required', 'string', \Illuminate\Validation\Rule::in($allowed)],
            'description' => ['nullable', 'string'],
        ]);

        $view->update([
            'name' => $data['name'],
            'viewpoint' => $data['viewpoint'],
            'description' => $data['description'] ?: null,
        ]);

        $this->modal('edit-design-view')->close();
        $this->dispatch('design-view-saved');
    }
}; ?>

<flux:modal name="edit-design-view" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Edit design view') }}</flux:heading>
        </div>

        <flux:input wire:model="name" :label="__('Name')" required />

        <flux:select wire:model="viewpoint" :label="__('Viewpoint')">
            @foreach (\App\Models\DesignView::BUILTIN_VIEWPOINTS as $vp)
                <flux:select.option value="{{ $vp }}">{{ str_replace('_', ' ', $vp) }}</flux:select.option>
            @endforeach
            @foreach ($this->customViewpointOptions as $custom)
                <flux:select.option value="{{ $custom->name }}">{{ $custom->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:textarea wire:model="description" :label="__('Description')" rows="3" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
        </div>
    </form>
</flux:modal>
