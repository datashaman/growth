<?php

use App\Models\User;
use App\Support\WorkspaceContext;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Livewire;

new class extends Component
{
    public ?string $selectedWorkspaceId = null;

    public function mount(): void
    {
        /** @var User $user */
        $user = auth()->user();

        $this->selectedWorkspaceId = $user->active_workspace_id;
    }

    #[Computed]
    public function workspaces()
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->workspaces()
            ->orderBy('name')
            ->get(['workspaces.id', 'workspaces.name']);
    }

    public function updatedSelectedWorkspaceId(?string $value): void
    {
        if ($value === null) {
            return;
        }

        /** @var User $user */
        $user = auth()->user();

        $belongs = $user->workspaces()->where('workspaces.id', $value)->exists();
        if (! $belongs) {
            $this->selectedWorkspaceId = $user->active_workspace_id;

            return;
        }

        $user->forceFill(['active_workspace_id' => $value])->save();
        app(WorkspaceContext::class)->forget();

        session()->forget('selected_project_id');

        $this->redirect('/'.ltrim(Livewire::originalPath(), '/'), navigate: true);
    }
}; ?>

<div @class(['hidden' => $this->workspaces->count() <= 1])>
    <flux:select wire:model.live="selectedWorkspaceId" size="sm" :placeholder="__('Select a workspace')">
        @foreach ($this->workspaces as $workspace)
            <flux:select.option value="{{ $workspace->id }}">{{ $workspace->name }}</flux:select.option>
        @endforeach
    </flux:select>
</div>
