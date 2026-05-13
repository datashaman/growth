<?php

use App\Models\Project;
use App\Models\Release;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $projectId = null;

    public string $version = '';
    public string $name = '';
    public string $status = 'planned';
    public string $released_at = '';
    public string $notes = '';

    public function mount(?string $projectId = null): void
    {
        $this->projectId = $projectId;
    }

    #[Computed]
    public function project(): ?Project
    {
        return $this->projectId ? Project::find($this->projectId) : null;
    }

    public function save(): void
    {
        $project = $this->project;

        abort_if($project === null, 404);

        $data = $this->validate([
            'version' => [
                'required', 'string', 'max:255',
                function (string $attribute, mixed $value, callable $fail) use ($project) {
                    if (Release::query()->where('project_id', $project->id)->where('version', $value)->exists()) {
                        $fail(__('A release with this version already exists in this project.'));
                    }
                },
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(Release::STATUSES)],
            'released_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $project->releases()->create([
            'version' => $data['version'],
            'name' => $data['name'] ?: null,
            'status' => $data['status'],
            'released_at' => $data['released_at'] ?: null,
            'notes' => $data['notes'] ?: null,
        ]);

        $this->reset(['version', 'name', 'released_at', 'notes']);
        $this->modal('create-release')->close();
        $this->dispatch('release-saved');
    }
}; ?>

<flux:modal name="create-release" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('New release') }}</flux:heading>
        </div>

        <flux:input wire:model="version" :label="__('Version')" :placeholder="__('e.g. 1.4.0')" required />
        <flux:input wire:model="name" :label="__('Name')" :placeholder="__('Optional codename')" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:select wire:model="status" :label="__('Status')">
                @foreach (\App\Models\Release::STATUSES as $option)
                    <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="released_at" type="date" :label="__('Released at')" />
        </div>

        <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Create release') }}</flux:button>
        </div>
    </form>
</flux:modal>
