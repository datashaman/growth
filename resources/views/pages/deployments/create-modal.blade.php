<?php

use App\Models\Deployment;
use App\Models\Project;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $projectId = null;

    public string $environment = '';
    public string $status = 'planned';
    public string $deployed_at = '';
    public string $url = '';
    public string $notes = '';
    public ?string $release_id = null;

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
    public function releaseOptions()
    {
        return $this->project?->releases()->orderBy('version')->get(['id', 'version', 'name']) ?? collect();
    }

    public function save(): void
    {
        $project = $this->project;

        abort_if($project === null, 404);

        $data = $this->validate([
            'environment' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(Deployment::STATUSES)],
            'deployed_at' => ['nullable', 'date'],
            'url' => ['nullable', 'url'],
            'notes' => ['nullable', 'string'],
            'release_id' => [
                'nullable',
                Rule::exists('releases', 'id')->where('project_id', $project->id),
            ],
        ]);

        $project->deployments()->create([
            'environment' => $data['environment'],
            'status' => $data['status'],
            'deployed_at' => $data['deployed_at'] ?: null,
            'url' => $data['url'] ?: null,
            'notes' => $data['notes'] ?: null,
            'release_id' => $data['release_id'] ?: null,
        ]);

        $this->reset(['environment', 'deployed_at', 'url', 'notes', 'release_id']);
        $this->modal('create-deployment')->close();
        $this->dispatch('deployment-saved');
    }
}; ?>

<flux:modal name="create-deployment" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('New deployment') }}</flux:heading>
        </div>

        <flux:input wire:model="environment" :label="__('Environment')" :placeholder="__('e.g. staging, production')" required />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:select wire:model="status" :label="__('Status')">
                @foreach (\App\Models\Deployment::STATUSES as $option)
                    <flux:select.option value="{{ $option }}">{{ str_replace('_', ' ', $option) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="deployed_at" type="date" :label="__('Deployed at')" />
        </div>

        <flux:select wire:model="release_id" :label="__('Release')">
            <flux:select.option value="">{{ __('— None —') }}</flux:select.option>
            @foreach ($this->releaseOptions as $release)
                <flux:select.option value="{{ $release->id }}">{{ $release->version }}@if ($release->name) — {{ $release->name }} @endif</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="url" :label="__('URL')" type="url" :placeholder="__('https://...')" />

        <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Create deployment') }}</flux:button>
        </div>
    </form>
</flux:modal>
