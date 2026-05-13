<?php

use App\Models\Release;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $releaseId = null;

    public string $version = '';
    public string $name = '';
    public string $status = 'planned';
    public string $released_at = '';
    public string $notes = '';

    #[On('edit-release')]
    public function load(string $releaseId): void
    {
        $release = Release::find($releaseId);

        abort_if($release === null, 404);

        $this->releaseId = $releaseId;
        $this->version = $release->version;
        $this->name = (string) $release->name;
        $this->status = $release->status;
        $this->released_at = $release->released_at?->format('Y-m-d') ?? '';
        $this->notes = (string) $release->notes;

        $this->modal('edit-release')->show();
    }

    #[Computed]
    public function release(): ?Release
    {
        return $this->releaseId ? Release::find($this->releaseId) : null;
    }

    public function save(): void
    {
        $release = $this->release;

        abort_if($release === null, 404);

        $data = $this->validate([
            'version' => [
                'required', 'string', 'max:255',
                function (string $attribute, mixed $value, callable $fail) use ($release) {
                    if (Release::query()
                        ->where('project_id', $release->project_id)
                        ->where('id', '!=', $release->id)
                        ->where('version', $value)
                        ->exists()
                    ) {
                        $fail(__('A release with this version already exists in this project.'));
                    }
                },
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(Release::STATUSES)],
            'released_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $release->update([
            'version' => $data['version'],
            'name' => $data['name'] ?: null,
            'status' => $data['status'],
            'released_at' => $data['released_at'] ?: null,
            'notes' => $data['notes'] ?: null,
        ]);

        $this->modal('edit-release')->close();
        $this->dispatch('release-saved');
    }
}; ?>

<flux:modal name="edit-release" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Edit release') }}</flux:heading>
        </div>

        <flux:input wire:model="version" :label="__('Version')" required />
        <flux:input wire:model="name" :label="__('Name')" />

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
            <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
        </div>
    </form>
</flux:modal>
