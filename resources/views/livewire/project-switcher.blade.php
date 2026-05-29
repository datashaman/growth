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
            ?? $this->orderedProjectsQuery()->value('id');
    }

    #[Computed]
    public function projects()
    {
        return $this->orderedProjectsQuery()->get(['id', 'name']);
    }

    private function orderedProjectsQuery()
    {
        return Project::query()
            ->orderByRaw('lower(projects.name)')
            ->orderBy('projects.name')
            ->orderBy('projects.id');
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
    </div>
</div>
