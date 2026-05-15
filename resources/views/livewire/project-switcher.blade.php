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

<div class="flex w-full flex-col gap-1.5">
    <div class="flex items-center gap-1">
        @if ($this->projects->isEmpty())
            <div class="flex flex-1 items-center gap-2 px-3 text-zinc-500 dark:text-zinc-400">
                <flux:icon.folder class="size-4 shrink-0" />
                <flux:text size="sm">{{ __('No projects') }}</flux:text>
            </div>
        @else
            <div class="relative flex-1">
                <flux:icon.folder class="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-zinc-500 dark:text-zinc-400" />
                <flux:select wire:model.live="selectedProjectId" size="sm" class="w-full ps-8" :placeholder="__('Select a project')">
                    @foreach ($this->projects as $project)
                        <flux:select.option value="{{ $project->id }}">{{ $project->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif
        <flux:modal.trigger name="create-project">
            <flux:button size="sm" variant="ghost" icon="plus" :tooltip="__('New project')" />
        </flux:modal.trigger>
    </div>

    <livewire:pages::projects.create-modal />
    <livewire:pages::projects.edit-modal />
    <livewire:pages::projects.move-modal />
    <livewire:pages::projects.delete-modal />
</div>
