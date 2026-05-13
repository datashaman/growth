<?php

use App\Models\Project;
use App\Models\Risk;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $projectId = null;

    public string $title = '';
    public string $description = '';
    public string $category = 'technical';
    public string $probability = 'medium';
    public string $impact = 'medium';
    public string $status = 'identified';
    public string $mitigation_plan = '';
    public ?string $owner_role_id = null;

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
    public function roleOptions()
    {
        return $this->project?->roles()->orderBy('name')->get(['id', 'name']) ?? collect();
    }

    public function save(): void
    {
        $project = $this->project;

        abort_if($project === null, 404);

        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', Rule::in(Risk::CATEGORIES)],
            'probability' => ['required', Rule::in(Risk::EXPOSURES)],
            'impact' => ['required', Rule::in(Risk::EXPOSURES)],
            'status' => ['required', Rule::in(Risk::STATUSES)],
            'mitigation_plan' => ['nullable', 'string'],
            'owner_role_id' => [
                'nullable',
                Rule::exists('roles', 'id')->where('project_id', $project->id),
            ],
        ]);

        $risk = $project->risks()->create([
            ...$data,
            'description' => $data['description'] ?: null,
            'mitigation_plan' => $data['mitigation_plan'] ?: null,
        ]);

        $this->reset(['title', 'description', 'mitigation_plan', 'owner_role_id']);
        $this->modal('create-risk')->close();

        $this->redirectRoute('risks.show', ['risk' => $risk->id], navigate: true);
    }
}; ?>

<flux:modal name="create-risk" :show="$errors->isNotEmpty()" focusable class="max-w-2xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('New risk') }}</flux:heading>
            <flux:subheading>{{ __('Track a risk for this project.') }}</flux:subheading>
        </div>

        <flux:input wire:model="title" :label="__('Title')" required />

        <flux:textarea wire:model="description" :label="__('Description')" rows="3" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:select wire:model="category" :label="__('Category')">
                @foreach (\App\Models\Risk::CATEGORIES as $option)
                    <flux:select.option value="{{ $option }}">{{ str_replace('_', ' ', $option) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="status" :label="__('Status')">
                @foreach (\App\Models\Risk::STATUSES as $option)
                    <flux:select.option value="{{ $option }}">{{ str_replace('_', ' ', $option) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="probability" :label="__('Probability')">
                @foreach (\App\Models\Risk::EXPOSURES as $option)
                    <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="impact" :label="__('Impact')">
                @foreach (\App\Models\Risk::EXPOSURES as $option)
                    <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="owner_role_id" :label="__('Owner role')" class="sm:col-span-2">
                <flux:select.option value="">{{ __('— None —') }}</flux:select.option>
                @foreach ($this->roleOptions as $role)
                    <flux:select.option value="{{ $role->id }}">{{ $role->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:textarea wire:model="mitigation_plan" :label="__('Mitigation plan')" rows="3" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Create risk') }}</flux:button>
        </div>
    </form>
</flux:modal>
