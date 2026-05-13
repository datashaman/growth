<?php

use App\Models\TestCase;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $testCaseId = null;

    public string $name = '';
    public string $objective = '';
    public string $expected_results = '';
    public string $environment = '';
    public string $preconditions_text = '';
    public string $inputs_text = '';

    #[On('edit-test-case')]
    public function load(string $testCaseId): void
    {
        $case = TestCase::find($testCaseId);

        abort_if($case === null, 404);

        $this->testCaseId = $testCaseId;
        $this->name = $case->name;
        $this->objective = (string) $case->objective;
        $this->expected_results = (string) $case->expected_results;
        $this->environment = (string) $case->environment;
        $this->preconditions_text = is_array($case->preconditions) ? implode("\n", $case->preconditions) : '';
        $this->inputs_text = is_array($case->inputs) ? implode("\n", $case->inputs) : '';

        $this->modal('edit-test-case')->show();
    }

    #[Computed]
    public function testCase(): ?TestCase
    {
        return $this->testCaseId ? TestCase::find($this->testCaseId) : null;
    }

    public function save(): void
    {
        $case = $this->testCase;

        abort_if($case === null, 404);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'objective' => ['nullable', 'string'],
            'expected_results' => ['required', 'string'],
            'environment' => ['nullable', 'string'],
            'preconditions_text' => ['nullable', 'string'],
            'inputs_text' => ['nullable', 'string'],
        ]);

        $case->update([
            'name' => $data['name'],
            'objective' => $data['objective'] ?: null,
            'expected_results' => $data['expected_results'],
            'environment' => $data['environment'] ?: null,
            'preconditions' => $this->splitLines($data['preconditions_text'] ?? ''),
            'inputs' => $this->splitLines($data['inputs_text'] ?? ''),
        ]);

        $this->modal('edit-test-case')->close();
        $this->dispatch('test-case-saved');
    }

    /**
     * @return array<int, string>|null
     */
    private function splitLines(string $text): ?array
    {
        $lines = collect(preg_split('/\r?\n/', $text))
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();

        return $lines === [] ? null : $lines;
    }
}; ?>

<flux:modal name="edit-test-case" :show="$errors->isNotEmpty()" focusable class="max-w-xl">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Edit test case') }}</flux:heading>
        </div>

        <flux:input wire:model="name" :label="__('Name')" required />
        <flux:textarea wire:model="objective" :label="__('Objective')" rows="2" />
        <flux:textarea wire:model="expected_results" :label="__('Expected results')" rows="3" required />
        <flux:input wire:model="environment" :label="__('Environment')" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:textarea wire:model="preconditions_text" :label="__('Preconditions')" :placeholder="__('One per line')" rows="3" />
            <flux:textarea wire:model="inputs_text" :label="__('Inputs')" :placeholder="__('One per line')" rows="3" />
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
        </div>
    </form>
</flux:modal>
