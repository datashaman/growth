<?php

use App\Models\Project;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public ?string $projectId = null;

    public string $name = '';

    public string $destinationId = '';

    #[On('move-project')]
    public function load(string $projectId): void
    {
        $project = Project::find($projectId);

        abort_if($project === null, 404);

        $this->projectId = $projectId;
        $this->name = $project->name;
        $this->destinationId = '';

        $this->modal('move-project')->show();
    }

    #[Computed]
    public function destinations(): Collection
    {
        $userId = auth()->id();
        $currentWorkspaceId = auth()->user()->active_workspace_id;

        return Workspace::query()
            ->whereIn('id', WorkspaceMembership::query()
                ->where('user_id', $userId)
                ->whereIn('role', [WorkspaceMembership::ROLE_OWNER, WorkspaceMembership::ROLE_ADMIN])
                ->pluck('workspace_id'))
            ->where('id', '!=', $currentWorkspaceId)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function move(): void
    {
        $this->validate([
            'destinationId' => ['required', 'string'],
        ]);

        $project = Project::find($this->projectId);

        abort_if($project === null, 404);

        $user = auth()->user();

        $project->move($this->destinationId, $user);
        $user->switchWorkspace($this->destinationId);

        $this->modal('move-project')->close();
        $this->redirect(route('dashboard'), navigate: false);
    }
}; ?>

<flux:modal name="move-project" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form wire:submit="move" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Move project') }}</flux:heading>
            @if ($name)
                <flux:subheading>{{ __('Transfer ":name" to another workspace.', ['name' => $name]) }}</flux:subheading>
            @endif
        </div>

        @if ($this->destinations->isEmpty())
            <flux:callout icon="information-circle" color="zinc">
                <flux:callout.heading>{{ __('No eligible destinations') }}</flux:callout.heading>
                <flux:callout.text>{{ __('You can only move projects to workspaces where you are an owner or admin.') }}</flux:callout.text>
            </flux:callout>
        @else
            <flux:select wire:model="destinationId" :label="__('Destination workspace')" required data-test="move-destination">
                <flux:select.option value="">{{ __('Select a workspace…') }}</flux:select.option>
                @foreach ($this->destinations as $workspace)
                    <flux:select.option value="{{ $workspace->id }}">{{ $workspace->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            @if ($this->destinations->isNotEmpty())
                <flux:button type="submit" variant="primary" data-test="confirm-move-project">
                    {{ __('Move project') }}
                </flux:button>
            @endif
        </div>
    </form>
</flux:modal>
