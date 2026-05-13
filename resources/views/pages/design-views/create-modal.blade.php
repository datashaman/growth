<?php

use App\Models\DesignView;
use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $projectId = null;

    public string $name = '';
    public string $viewpoint = 'logical';
    public string $description = '';

    public function mount(?string $projectId = null): void
    {
        $this->projectId = $projectId;
    }

    #[Computed]
    public function project(): ?Project
    {
        return $this->projectId ? Project::find($this->projectId) : null;
    }

    #[Computed]
    public function customViewpointOptions()
    {
        return $this->project?->customViewpoints()->orderBy('name')->get(['id', 'name']) ?? collect();
    }

    public function save(): void
    {
        $project = $this->project;

        abort_if($project === null, 404);

        $allowed = array_merge(
            DesignView::BUILTIN_VIEWPOINTS,
            $this->customViewpointOptions->pluck('name')->all(),
        );

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'viewpoint' => ['required', 'string', \Illuminate\Validation\Rule::in($allowed)],
            'description' => ['nullable', 'string'],
        ]);

        $project->designViews()->create([
            'name' => $data['name'],
            'viewpoint' => $data['viewpoint'],
            'description' => $data['description'] ?: null,
        ]);

        $this->reset(['name', 'description']);
        $this->modal('create-design-view')->close();
        $this->dispatch('design-view-saved');
    }
}; ?>

<flux:modal name="create-design-view" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('New design view') }}</flux:heading>
            <flux:subheading>{{ __('A coherent view of the system from a specific viewpoint.') }}</flux:subheading>
        </div>

        <flux:input wire:model="name" :label="__('Name')" required />

        <flux:select wire:model="viewpoint" :label="__('Viewpoint')">
            <flux:select.option value="" disabled>{{ __('— Built-in —') }}</flux:select.option>
            @foreach (\App\Models\DesignView::BUILTIN_VIEWPOINTS as $vp)
                <flux:select.option value="{{ $vp }}">{{ str_replace('_', ' ', $vp) }}</flux:select.option>
            @endforeach
            @if ($this->customViewpointOptions->isNotEmpty())
                <flux:select.option value="" disabled>{{ __('— Custom —') }}</flux:select.option>
                @foreach ($this->customViewpointOptions as $custom)
                    <flux:select.option value="{{ $custom->name }}">{{ $custom->name }}</flux:select.option>
                @endforeach
            @endif
        </flux:select>

        <flux:textarea wire:model="description" :label="__('Description')" rows="3" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Create view') }}</flux:button>
        </div>
    </form>
</flux:modal>
