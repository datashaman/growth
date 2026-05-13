<?php

use App\Models\Anomaly;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public string $anomalyId;

    public string $summary = '';
    public string $description = '';
    public string $severity = 'medium';
    public string $status = 'open';
    public string $environment = '';

    public function mount(string $anomalyId): void
    {
        $this->anomalyId = $anomalyId;
        $anomaly = $this->anomaly;

        abort_if($anomaly === null, 404);

        $this->summary = $anomaly->summary;
        $this->description = $anomaly->description;
        $this->severity = $anomaly->severity;
        $this->status = $anomaly->status;
        $this->environment = (string) $anomaly->environment;
    }

    #[Computed]
    public function anomaly(): ?Anomaly
    {
        return Anomaly::find($this->anomalyId);
    }

    public function save(): void
    {
        $anomaly = $this->anomaly;

        abort_if($anomaly === null, 404);

        $data = $this->validate([
            'summary' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'severity' => ['required', Rule::in(Anomaly::SEVERITIES)],
            'status' => ['required', Rule::in(Anomaly::STATUSES)],
            'environment' => ['nullable', 'string'],
        ]);

        $anomaly->update([
            ...$data,
            'environment' => $data['environment'] ?: null,
        ]);

        $this->modal('edit-anomaly')->close();
        $this->redirectRoute('anomalies.show', ['anomaly' => $anomaly->id], navigate: true);
    }
}; ?>

<flux:modal name="edit-anomaly" :show="$errors->isNotEmpty()" focusable class="max-w-2xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Edit anomaly') }}</flux:heading>
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

        <flux:input wire:model="environment" :label="__('Environment')" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
        </div>
    </form>
</flux:modal>
