<?php

use App\Models\Deployment;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $deploymentId = null;

    public string $environment = '';
    public string $status = 'planned';
    public string $deployed_at = '';
    public string $url = '';
    public string $notes = '';
    public ?string $release_id = null;

    #[On('edit-deployment')]
    public function load(string $deploymentId): void
    {
        $deployment = Deployment::find($deploymentId);

        abort_if($deployment === null, 404);

        $this->deploymentId = $deploymentId;
        $this->environment = $deployment->environment;
        $this->status = $deployment->status;
        $this->deployed_at = $deployment->deployed_at?->format('Y-m-d') ?? '';
        $this->url = (string) $deployment->url;
        $this->notes = (string) $deployment->notes;
        $this->release_id = $deployment->release_id;

        $this->modal('edit-deployment')->show();
    }

    #[Computed]
    public function deployment(): ?Deployment
    {
        return $this->deploymentId ? Deployment::find($this->deploymentId) : null;
    }

    #[Computed]
    public function releaseOptions()
    {
        return $this->deployment?->project->releases()->orderBy('version')->get(['id', 'version', 'name']) ?? collect();
    }

    public function save(): void
    {
        $deployment = $this->deployment;

        abort_if($deployment === null, 404);

        $data = $this->validate([
            'environment' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(Deployment::STATUSES)],
            'deployed_at' => ['nullable', 'date'],
            'url' => ['nullable', 'url'],
            'notes' => ['nullable', 'string'],
            'release_id' => [
                'nullable',
                Rule::exists('releases', 'id')->where('project_id', $deployment->project_id),
            ],
        ]);

        $deployment->update([
            'environment' => $data['environment'],
            'status' => $data['status'],
            'deployed_at' => $data['deployed_at'] ?: null,
            'url' => $data['url'] ?: null,
            'notes' => $data['notes'] ?: null,
            'release_id' => $data['release_id'] ?: null,
        ]);

        $this->modal('edit-deployment')->close();
        $this->dispatch('deployment-saved');
    }
}; ?>

<flux:modal name="edit-deployment" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Edit deployment') }}</flux:heading>
        </div>

        <flux:input wire:model="environment" :label="__('Environment')" required />

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

        <flux:input wire:model="url" :label="__('URL')" type="url" />

        <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
        </div>
    </form>
</flux:modal>
