<?php

use App\Models\Project;
use App\Support\WorkspaceContext;
use Livewire\Component;

new class extends Component {
    public string $name = '';
    public string $description = '';
    public int $rigor_level = 2;

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'rigor_level' => ['required', 'integer', 'between:1,4'],
        ]);

        $project = Project::create([
            'workspace_id' => app(WorkspaceContext::class)->requireId(),
            'created_by_user_id' => auth()->id(),
            'name' => $data['name'],
            'description' => $data['description'] ?: null,
            'rigor_level' => $data['rigor_level'],
        ]);

        session(['selected_project_id' => $project->id]);

        $this->reset(['name', 'description', 'rigor_level']);
        $this->modal('create-project')->close();
        $this->redirect(route('dashboard'), navigate: true);
    }
}; ?>

<flux:modal name="create-project" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('New project') }}</flux:heading>
            <flux:subheading>{{ __('Projects are the top-level container for stakeholders, requirements, work, and evidence.') }}</flux:subheading>
        </div>

        <flux:input wire:model="name" :label="__('Name')" required />

        <flux:textarea wire:model="description" :label="__('Description')" rows="3" />

        <flux:select wire:model="rigor_level" :label="__('Rigor level')" :description="__('1 = lowest rigor, 4 = highest. Drives readiness gates and review depth.')">
            <flux:select.option value="1">{{ __('1 — Minimal rigor') }}</flux:select.option>
            <flux:select.option value="2">{{ __('2 — Standard') }}</flux:select.option>
            <flux:select.option value="3">{{ __('3 — Elevated') }}</flux:select.option>
            <flux:select.option value="4">{{ __('4 — Safety-critical') }}</flux:select.option>
        </flux:select>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Create project') }}</flux:button>
        </div>
    </form>
</flux:modal>
