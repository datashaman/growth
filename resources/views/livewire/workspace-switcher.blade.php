<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\WorkspaceContext;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Livewire;

new class extends Component
{
    public ?string $selectedWorkspaceId = null;

    public string $newWorkspaceName = '';

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

    public function createWorkspace(): void
    {
        $validated = $this->validate([
            'newWorkspaceName' => ['required', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = auth()->user();

        $workspace = Workspace::create([
            'name' => $validated['newWorkspaceName'],
            'slug' => Workspace::uniqueSlug($validated['newWorkspaceName']),
            'owner_user_id' => $user->id,
        ]);

        WorkspaceMembership::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMembership::ROLE_OWNER,
        ]);

        $user->forceFill(['active_workspace_id' => $workspace->id])->save();
        app(WorkspaceContext::class)->forget();
        session()->forget('selected_project_id');

        $this->reset(['newWorkspaceName']);
        Flux::modal('create-workspace')->close();

        $this->redirect('/'.ltrim(Livewire::originalPath(), '/'), navigate: true);
    }
}; ?>

<div class="flex w-full flex-col gap-1.5">
    <div class="flex items-center gap-1">
        @if ($this->workspaces->count() <= 1)
            <div class="flex flex-1 items-center gap-2 truncate px-3">
                <flux:icon.building-office-2 class="size-4 shrink-0 text-zinc-500 dark:text-zinc-400" />
                <flux:text size="sm" class="truncate" data-test="single-workspace-name">{{ $this->workspaces->first()?->name }}</flux:text>
            </div>
        @else
            <div class="relative flex-1">
                <flux:icon.building-office-2 class="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-zinc-500 dark:text-zinc-400" />
                <flux:select wire:model.live="selectedWorkspaceId" size="sm" class="w-full ps-8" :placeholder="__('Select a workspace')">
                    @foreach ($this->workspaces as $workspace)
                        <flux:select.option value="{{ $workspace->id }}">{{ $workspace->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif

        <flux:modal.trigger name="create-workspace">
            <flux:button size="sm" variant="ghost" icon="plus" :tooltip="__('New workspace')" data-test="sidebar-new-workspace" />
        </flux:modal.trigger>
    </div>

    <flux:modal name="create-workspace" class="md:w-96">
        <form wire:submit="createWorkspace" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('New workspace') }}</flux:heading>
                <flux:subheading>{{ __('Give your workspace a name. You can change it later.') }}</flux:subheading>
            </div>

            <flux:input
                wire:model="newWorkspaceName"
                :label="__('Name')"
                type="text"
                required
                data-test="sidebar-new-workspace-name"
            />

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit" data-test="sidebar-confirm-new-workspace">
                    {{ __('Create') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
