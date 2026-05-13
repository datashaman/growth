<?php

use App\Models\Project;
use App\Models\Review;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $projectId = null;

    public string $title = '';
    public string $type = 'technical_review';
    public string $status = 'planned';
    public string $objective = '';
    public string $summary = '';
    public string $planned_at = '';
    public string $held_at = '';
    public ?string $decision = null;
    public ?string $owner_role_id = null;
    public ?string $review_plan_id = null;
    public string $entry_criteria_text = '';
    public string $exit_criteria_text = '';

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
    public function reviewPlanOptions()
    {
        return $this->project?->reviewPlans()->orderBy('name')->get(['id', 'name']) ?? collect();
    }

    public function save(): void
    {
        $project = $this->project;

        abort_if($project === null, 404);

        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Review::TYPES)],
            'status' => ['required', Rule::in(Review::STATUSES)],
            'objective' => ['nullable', 'string'],
            'summary' => ['nullable', 'string'],
            'planned_at' => ['nullable', 'date'],
            'held_at' => ['nullable', 'date'],
            'decision' => ['nullable', Rule::in(Review::DECISIONS)],
            'owner_role_id' => [
                'nullable',
                Rule::exists('roles', 'id')->where('project_id', $project->id),
            ],
            'review_plan_id' => [
                'nullable',
                Rule::exists('review_plans', 'id')->where('project_id', $project->id),
            ],
            'entry_criteria_text' => ['nullable', 'string'],
            'exit_criteria_text' => ['nullable', 'string'],
        ]);

        $review = $project->reviews()->create([
            'title' => $data['title'],
            'type' => $data['type'],
            'status' => $data['status'],
            'objective' => $data['objective'] ?: null,
            'summary' => $data['summary'] ?: null,
            'planned_at' => $data['planned_at'] ?: null,
            'held_at' => $data['held_at'] ?: null,
            'decision' => $data['decision'] ?: null,
            'owner_role_id' => $data['owner_role_id'] ?: null,
            'review_plan_id' => $data['review_plan_id'] ?: null,
            'entry_criteria' => $this->splitCriteria($data['entry_criteria_text'] ?? ''),
            'exit_criteria' => $this->splitCriteria($data['exit_criteria_text'] ?? ''),
        ]);

        $this->reset();
        $this->modal('create-review')->close();

        $this->redirectRoute('reviews.show', ['review' => $review->id], navigate: true);
    }

    /**
     * @return array<int, string>|null
     */
    private function splitCriteria(string $text): ?array
    {
        $lines = collect(preg_split('/\r?\n/', $text))
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();

        return $lines === [] ? null : $lines;
    }
}; ?>

<flux:modal name="create-review" :show="$errors->isNotEmpty()" focusable class="max-w-2xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('New review') }}</flux:heading>
        </div>

        <flux:input wire:model="title" :label="__('Title')" required />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:select wire:model="type" :label="__('Type')">
                @foreach (\App\Models\Review::TYPES as $option)
                    <flux:select.option value="{{ $option }}">{{ str_replace('_', ' ', $option) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="status" :label="__('Status')">
                @foreach (\App\Models\Review::STATUSES as $option)
                    <flux:select.option value="{{ $option }}">{{ str_replace('_', ' ', $option) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="planned_at" type="date" :label="__('Planned')" />
            <flux:input wire:model="held_at" type="date" :label="__('Held')" />

            <flux:select wire:model="decision" :label="__('Decision')">
                <flux:select.option value="">{{ __('— None —') }}</flux:select.option>
                @foreach (\App\Models\Review::DECISIONS as $option)
                    <flux:select.option value="{{ $option }}">{{ str_replace('_', ' ', $option) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="owner_role_id" :label="__('Owner role')">
                <flux:select.option value="">{{ __('— None —') }}</flux:select.option>
                @foreach ($this->roleOptions as $role)
                    <flux:select.option value="{{ $role->id }}">{{ $role->name }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($this->reviewPlanOptions->isNotEmpty())
                <flux:select wire:model="review_plan_id" :label="__('Review plan')" class="sm:col-span-2">
                    <flux:select.option value="">{{ __('— None —') }}</flux:select.option>
                    @foreach ($this->reviewPlanOptions as $plan)
                        <flux:select.option value="{{ $plan->id }}">{{ $plan->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
        </div>

        <flux:textarea wire:model="objective" :label="__('Objective')" rows="2" />
        <flux:textarea wire:model="summary" :label="__('Summary')" rows="2" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:textarea wire:model="entry_criteria_text" :label="__('Entry criteria')" :placeholder="__('One per line')" rows="3" />
            <flux:textarea wire:model="exit_criteria_text" :label="__('Exit criteria')" :placeholder="__('One per line')" rows="3" />
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Create review') }}</flux:button>
        </div>
    </form>
</flux:modal>
