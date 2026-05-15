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
        $fromSession = (string) session('selected_project_id', '');

        $this->selectedProjectId = match (true) {
            $fromQuery !== '' => $fromQuery,
            $fromSession !== '' => $fromSession,
            default => Project::query()->orderBy('created_at')->value('id'),
        };

        if ($this->selectedProjectId !== null && $this->selectedProjectId !== $fromSession) {
            session(['selected_project_id' => $this->selectedProjectId]);
        }
    }

    #[Computed]
    public function selectedProject(): ?Project
    {
        return $this->selectedProjectId
            ? Project::query()->find($this->selectedProjectId)
            : null;
    }

    /**
     * Echo subscription to broadcast changes for the currently-selected
     * project. The consuming component should merge this into its own
     * getListeners() and define an onProjectDataChanged() method that busts
     * its computed caches.
     *
     * @return array<string,string>
     */
    public function projectScopedListeners(): array
    {
        if ($this->selectedProjectId === null) {
            return [];
        }

        return [
            'echo-private:projects.'.$this->selectedProjectId.',ProjectDataChanged' => 'onProjectDataChanged',
        ];
    }
}
