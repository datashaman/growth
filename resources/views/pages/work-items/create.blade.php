<?php

use App\Models\Project;
use App\Models\WorkItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('New work item')] class extends Component {
    #[Locked]
    public string $projectId;

    public string $name = '';
    public string $kind = 'task';
    public string $status = 'todo';
    public string $description = '';
    public string $planned_start_date = '';
    public string $due_date = '';
    public ?string $responsible_role_id = null;
    public ?string $parent_id = null;

    public function mount(?string $project = null): void
    {
        $projectId = $project ?? (string) request()->query('project', '');
        $resolved = $projectId !== '' ? Project::find($projectId) : null;

        abort_if($resolved === null, 404);

        $this->projectId = $resolved->id;
    }

    #[Computed]
    public function project(): ?Project
    {
        return Project::find($this->projectId);
    }

    #[Computed]
    public function roleOptions()
    {
        return $this->project?->roles()->orderBy('name')->get(['id', 'name']) ?? collect();
    }

    #[Computed]
    public function parentOptions()
    {
        return $this->project?->workItems()->orderBy('name')->get(['id', 'name', 'kind']) ?? collect();
    }

    public function save(): void
    {
        $project = $this->project;

        abort_if($project === null, 404);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in(WorkItem::KINDS)],
            'status' => ['required', Rule::in(WorkItem::STATUSES)],
            'description' => ['nullable', 'string'],
            'planned_start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'responsible_role_id' => [
                'nullable',
                Rule::exists('roles', 'id')->where('project_id', $project->id),
            ],
            'parent_id' => [
                'nullable',
                Rule::exists('work_items', 'id')->where('project_id', $project->id),
            ],
        ]);

        $workItem = DB::transaction(fn () => $project->workItems()->create([
            'name' => $data['name'],
            'kind' => $data['kind'],
            'status' => $data['status'],
            'description' => $data['description'] ?: null,
            'planned_start_date' => $data['planned_start_date'] ?: null,
            'due_date' => $data['due_date'] ?: null,
            'responsible_role_id' => $data['responsible_role_id'] ?: null,
            'parent_id' => $data['parent_id'] ?: null,
        ]));

        $this->redirectRoute('work-items.show', ['workItem' => $workItem->id], navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-detail-page-header
        :title="__('New work item')"
        back-route="plan"
        :back-label="__('Cancel and return to plan')">
        <x-slot:description>
            {{ __('In project') }} <a href="{{ route('dashboard', ['project' => $this->project->id]) }}" class="underline">{{ $this->project->name }}</a>
        </x-slot:description>
    </x-detail-page-header>

    <form wire:submit="save" class="space-y-6">
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-3">{{ __('Details') }}</flux:heading>
            <div class="space-y-4">
                <flux:input wire:model="name" :label="__('Name')" required />
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <flux:select wire:model="kind" :label="__('Kind')">
                        @foreach (\App\Models\WorkItem::KINDS as $option)
                            <flux:select.option value="{{ $option }}">{{ str_replace('_', ' ', $option) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="status" :label="__('Status')">
                        @foreach (\App\Models\WorkItem::STATUSES as $option)
                            <flux:select.option value="{{ $option }}">{{ str_replace('_', ' ', $option) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="responsible_role_id" :label="__('Responsible role')">
                        <flux:select.option value="">{{ __('— None —') }}</flux:select.option>
                        @foreach ($this->roleOptions as $role)
                            <flux:select.option value="{{ $role->id }}">{{ $role->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:select wire:model="parent_id" :label="__('Parent work item')">
                    <flux:select.option value="">{{ __('— None —') }}</flux:select.option>
                    @foreach ($this->parentOptions as $option)
                        <flux:select.option value="{{ $option->id }}">{{ $option->name }} ({{ str_replace('_', ' ', $option->kind) }})</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:textarea wire:model="description" :label="__('Description')" rows="4" />
            </div>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-3">{{ __('Schedule') }}</flux:heading>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="planned_start_date" type="date" :label="__('Planned start')" />
                <flux:input wire:model="due_date" type="date" :label="__('Due')" />
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <flux:button :href="route('plan')" wire:navigate variant="filled">{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Create work item') }}</flux:button>
        </div>
    </form>
</div>
