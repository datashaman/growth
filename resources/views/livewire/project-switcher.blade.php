<?php

use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
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

    #[On('project-saved')]
    public function refreshProjects(): void
    {
        unset($this->projects);
    }

    public function updatedSelectedProjectId(?string $value): void
    {
        if ($value !== null) {
            session(['selected_project_id' => $value]);
        }

        $this->redirect('/'.ltrim(Livewire::originalPath(), '/'), navigate: true);
    }
}; ?>

<div class="flex w-full flex-col gap-1">
    <flux:text size="xs" class="px-3 uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Project') }}</flux:text>
    <div class="flex items-center gap-1">
        @if ($this->projects->isEmpty())
            <flux:text size="sm" class="flex-1 px-3 text-zinc-500 dark:text-zinc-400">{{ __('No projects') }}</flux:text>
        @else
            <flux:select wire:model.live="selectedProjectId" size="sm" class="flex-1" :placeholder="__('Select a project')">
                @foreach ($this->projects as $project)
                    <flux:select.option value="{{ $project->id }}">{{ $project->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
        <flux:modal.trigger name="create-project">
            <flux:button size="sm" variant="ghost" icon="plus" :tooltip="__('New project')" />
        </flux:modal.trigger>
    </div>

    <livewire:pages::projects.create-modal />
    <livewire:pages::projects.edit-modal />
    <livewire:pages::projects.delete-modal />
</div>
