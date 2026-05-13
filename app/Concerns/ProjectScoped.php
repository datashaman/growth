<?php

namespace App\Concerns;

use App\Models\Project;
use Livewire\Attributes\Computed;

trait ProjectScoped
{
    public ?string $selectedProjectId = null;

    public function mountProjectScoped(): void
    {
        $fromQuery = (string) request()->query('project', '');

        $this->selectedProjectId = $fromQuery !== ''
            ? $fromQuery
            : Project::query()->orderBy('created_at')->value('id');
    }

    #[Computed]
    public function projectOptions()
    {
        return Project::query()
            ->orderBy('created_at')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function selectedProject(): ?Project
    {
        return $this->selectedProjectId
            ? Project::query()->find($this->selectedProjectId)
            : null;
    }
}
