<?php

use App\Models\WorkItem;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit work item')] class extends Component {
    public WorkItem $workItem;

    public string $name = '';
    public string $kind = 'task';
    public string $status = 'todo';
    public string $description = '';
    public string $planned_start_date = '';
    public string $due_date = '';
    public ?string $responsible_role_id = null;
    public ?string $parent_id = null;

    public function mount(WorkItem $workItem): void
    {
        $this->workItem = $workItem;
        $this->name = $workItem->name;
        $this->kind = $workItem->kind;
        $this->status = $workItem->status;
        $this->description = (string) $workItem->description;
        $this->planned_start_date = $workItem->planned_start_date?->format('Y-m-d') ?? '';
        $this->due_date = $workItem->due_date?->format('Y-m-d') ?? '';
        $this->responsible_role_id = $workItem->responsible_role_id;
        $this->parent_id = $workItem->parent_id;
    }

    #[Computed]
    public function roleOptions()
    {
        return $this->workItem->project->roles()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function parentOptions()
    {
        return $this->workItem->project->workItems()
            ->where('id', '!=', $this->workItem->id)
            ->orderBy('name')
            ->get(['id', 'name', 'kind']);
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in(WorkItem::KINDS)],
            'status' => ['required', Rule::in(WorkItem::STATUSES)],
            'description' => ['nullable', 'string'],
            'planned_start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'responsible_role_id' => [
                'nullable',
                Rule::exists('roles', 'id')->where('project_id', $this->workItem->project_id),
            ],
            'parent_id' => [
                'nullable',
                Rule::exists('work_items', 'id')->where('project_id', $this->workItem->project_id),
            ],
        ]);

        $this->workItem->update([
            'name' => $data['name'],
            'kind' => $data['kind'],
            'status' => $data['status'],
            'description' => $data['description'] ?: null,
            'planned_start_date' => $data['planned_start_date'] ?: null,
            'due_date' => $data['due_date'] ?: null,
            'responsible_role_id' => $data['responsible_role_id'] ?: null,
            'parent_id' => $data['parent_id'] ?: null,
        ]);

        $this->redirectRoute('work-items.show', ['workItem' => $this->workItem->id], navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-detail-page-header
        :title="__('Edit work item')"
        :back-href="route('work-items.show', $workItem)"
        :back-label="__('Cancel and return to work item')">
        <x-slot:description>
            {{ __('In project') }} <a href="{{ route('dashboard', ['project' => $workItem->project_id]) }}" class="underline">{{ $workItem->project->name }}</a>
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
            <flux:button :href="route('work-items.show', $workItem)" wire:navigate variant="filled">{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
        </div>
    </form>
</div>
