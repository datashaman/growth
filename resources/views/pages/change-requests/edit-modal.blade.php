<?php

use App\Models\ChangeRequest;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $changeRequestId = null;

    public string $title = '';
    public string $description = '';
    public string $rationale = '';
    public string $category = 'scope';
    public string $status = 'proposed';
    public string $priority = 'medium';
    public ?string $decision = null;
    public string $decision_rationale = '';
    public string $decided_at = '';
    public ?string $requester_role_id = null;
    public ?string $review_id = null;

    #[On('edit-change-request')]
    public function load(string $changeRequestId): void
    {
        $cr = ChangeRequest::find($changeRequestId);

        abort_if($cr === null, 404);

        $this->changeRequestId = $changeRequestId;
        $this->title = $cr->title;
        $this->description = (string) $cr->description;
        $this->rationale = (string) $cr->rationale;
        $this->category = $cr->category;
        $this->status = $cr->status;
        $this->priority = $cr->priority;
        $this->decision = $cr->decision;
        $this->decision_rationale = (string) $cr->decision_rationale;
        $this->decided_at = $cr->decided_at?->format('Y-m-d') ?? '';
        $this->requester_role_id = $cr->requester_role_id;
        $this->review_id = $cr->review_id;

        $this->modal('edit-change-request')->show();
    }

    #[Computed]
    public function changeRequest(): ?ChangeRequest
    {
        return $this->changeRequestId ? ChangeRequest::find($this->changeRequestId) : null;
    }

    #[Computed]
    public function roleOptions()
    {
        return $this->changeRequest?->project->roles()->orderBy('name')->get(['id', 'name']) ?? collect();
    }

    #[Computed]
    public function reviewOptions()
    {
        return $this->changeRequest?->project->reviews()->orderByDesc('planned_at')->get(['id', 'title']) ?? collect();
    }

    public function save(): void
    {
        $cr = $this->changeRequest;

        abort_if($cr === null, 404);

        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'rationale' => ['nullable', 'string'],
            'category' => ['required', Rule::in(ChangeRequest::CATEGORIES)],
            'status' => ['required', Rule::in(ChangeRequest::STATUSES)],
            'priority' => ['required', Rule::in(ChangeRequest::PRIORITIES)],
            'decision' => ['nullable', Rule::in(ChangeRequest::DECISIONS)],
            'decision_rationale' => ['nullable', 'string'],
            'decided_at' => ['nullable', 'date'],
            'requester_role_id' => [
                'nullable',
                Rule::exists('roles', 'id')->where('project_id', $cr->project_id),
            ],
            'review_id' => [
                'nullable',
                Rule::exists('reviews', 'id')->where('project_id', $cr->project_id),
            ],
        ]);

        $cr->update([
            'title' => $data['title'],
            'description' => $data['description'] ?: null,
            'rationale' => $data['rationale'] ?: null,
            'category' => $data['category'],
            'status' => $data['status'],
            'priority' => $data['priority'],
            'decision' => $data['decision'] ?: null,
            'decision_rationale' => $data['decision_rationale'] ?: null,
            'decided_at' => $data['decided_at'] ?: null,
            'requester_role_id' => $data['requester_role_id'] ?: null,
            'review_id' => $data['review_id'] ?: null,
        ]);

        $this->modal('edit-change-request')->close();
        $this->dispatch('change-request-saved');
    }
}; ?>

<flux:modal name="edit-change-request" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Edit change request') }}</flux:heading>
        </div>

        <flux:input wire:model="title" :label="__('Title')" required />

        <flux:textarea wire:model="description" :label="__('Description')" rows="3" />
        <flux:textarea wire:model="rationale" :label="__('Rationale')" rows="2" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <flux:select wire:model="category" :label="__('Category')">
                @foreach (\App\Models\ChangeRequest::CATEGORIES as $option)
                    <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="status" :label="__('Status')">
                @foreach (\App\Models\ChangeRequest::STATUSES as $option)
                    <flux:select.option value="{{ $option }}">{{ str_replace('_', ' ', $option) }}</flux:select.option>
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

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:select wire:model="decision" :label="__('Decision')">
                <flux:select.option value="">{{ __('— Pending —') }}</flux:select.option>
                @foreach (\App\Models\ChangeRequest::DECISIONS as $option)
                    <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="decided_at" type="date" :label="__('Decided at')" />
        </div>

        <flux:textarea wire:model="decision_rationale" :label="__('Decision rationale')" rows="2" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
        </div>
    </form>
</flux:modal>
