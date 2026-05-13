<?php

use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Livewire;

new class extends Component {
    public ?string $selectedProjectId = null;

    public function mount(): void
    {
        $this->selectedProjectId = session('selected_project_id')
            ?? Project::query()->orderBy('created_at')->value('id');
    }

    #[Computed]
    public function projects()
    {
        return Project::query()->orderBy('created_at')->get(['id', 'name']);
    }

    public function updatedSelectedProjectId(?string $value): void
    {
        if ($value !== null) {
            session(['selected_project_id' => $value]);
        }

        $this->redirect('/'.ltrim(Livewire::originalPath(), '/'), navigate: true);
    }
}; ?>

<div class="w-full">
    @if ($this->projects->isEmpty())
        <flux:text size="sm" class="px-3 text-zinc-500 dark:text-zinc-400">{{ __('No projects') }}</flux:text>
    @else
        <flux:select wire:model.live="selectedProjectId" size="sm" class="w-full" :placeholder="__('Select a project')">
            @foreach ($this->projects as $project)
                <flux:select.option value="{{ $project->id }}">{{ $project->name }}</flux:select.option>
            @endforeach
        </flux:select>
    @endif
</div>
