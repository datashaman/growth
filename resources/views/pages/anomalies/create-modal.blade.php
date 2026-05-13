<?php

use App\Models\Anomaly;
use App\Models\Project;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $projectId = null;

    public string $summary = '';
    public string $description = '';
    public string $severity = 'medium';
    public string $status = 'open';
    public string $environment = '';

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
            'summary' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'severity' => ['required', Rule::in(Anomaly::SEVERITIES)],
            'status' => ['required', Rule::in(Anomaly::STATUSES)],
            'environment' => ['nullable', 'string'],
        ]);

        $anomaly = $project->anomalies()->create([
            ...$data,
            'environment' => $data['environment'] ?: null,
        ]);

        $this->reset(['summary', 'description', 'environment']);
        $this->modal('create-anomaly')->close();

        $this->redirectRoute('anomalies.show', ['anomaly' => $anomaly->id], navigate: true);
    }
}; ?>

<flux:modal name="create-anomaly" :show="$errors->isNotEmpty()" focusable class="max-w-2xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Report anomaly') }}</flux:heading>
            <flux:subheading>{{ __('Capture a defect or unexpected behaviour observed during testing.') }}</flux:subheading>
        </div>

        <flux:input wire:model="summary" :label="__('Summary')" required />

        <flux:textarea wire:model="description" :label="__('Description')" rows="4" required />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:select wire:model="severity" :label="__('Severity')">
                @foreach (\App\Models\Anomaly::SEVERITIES as $option)
                    <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="status" :label="__('Status')">
                @foreach (\App\Models\Anomaly::STATUSES as $option)
                    <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:input wire:model="environment" :label="__('Environment')" :placeholder="__('e.g. staging, prod, hardware rev B')" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Report anomaly') }}</flux:button>
        </div>
    </form>
</flux:modal>
