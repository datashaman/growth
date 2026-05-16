<?php

use App\Models\ChangeRequest;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $projectId = null;

    public string $title = '';
    public string $description = '';
    public string $rationale = '';
    public string $category = 'scope';
    public string $priority = 'medium';
    public ?string $requester_role_id = null;
    public ?string $review_id = null;

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

    #[Computed]
    public function reviewOptions()
    {
        return $this->project?->reviews()->orderByDesc('planned_at')->get(['id', 'title']) ?? collect();
    }

    public function save(): void
    {
        $project = $this->project;

        abort_if($project === null, 404);

        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'rationale' => ['nullable', 'string'],
            'category' => ['required', Rule::in(ChangeRequest::CATEGORIES)],
            'priority' => ['required', Rule::in(ChangeRequest::PRIORITIES)],
            'requester_role_id' => [
                'nullable',
                Rule::exists('roles', 'id')->where('project_id', $project->id),
            ],
            'review_id' => [
                'nullable',
                Rule::exists('reviews', 'id')->where('project_id', $project->id),
            ],
        ]);

        DB::transaction(fn () => $project->changeRequests()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?: null,
            'rationale' => $data['rationale'] ?: null,
            'category' => $data['category'],
            'priority' => $data['priority'],
            'requester_role_id' => $data['requester_role_id'] ?: null,
            'review_id' => $data['review_id'] ?: null,
        ]));

        $this->reset(['title', 'description', 'rationale', 'requester_role_id', 'review_id']);
        $this->modal('create-change-request')->close();
        $this->dispatch('change-request-saved');
    }
}; ?>

<flux:modal name="create-change-request" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('New change request') }}</flux:heading>
            <flux:subheading>{{ __('Track scope, requirements, design, or plan changes through review and approval.') }}</flux:subheading>
        </div>

        <flux:input wire:model="title" :label="__('Title')" required />

        <flux:textarea wire:model="description" :label="__('Description')" rows="3" />
        <flux:textarea wire:model="rationale" :label="__('Rationale')" rows="2" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:select wire:model="category" :label="__('Category')">
                @foreach (\App\Models\ChangeRequest::CATEGORIES as $option)
                    <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="priority" :label="__('Priority')">
                @foreach (\App\Models\ChangeRequest::PRIORITIES as $option)
                    <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:select wire:model="requester_role_id" :label="__('Requester role')">
                <flux:select.option value="">{{ __('— None —') }}</flux:select.option>
                @foreach ($this->roleOptions as $role)
                    <flux:select.option value="{{ $role->id }}">{{ $role->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="review_id" :label="__('Linked review')">
                <flux:select.option value="">{{ __('— None —') }}</flux:select.option>
                @foreach ($this->reviewOptions as $review)
                    <flux:select.option value="{{ $review->id }}">{{ $review->title }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Create change') }}</flux:button>
        </div>
    </form>
</flux:modal>
