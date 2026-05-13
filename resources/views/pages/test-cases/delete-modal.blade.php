<?php

use App\Models\TestCase;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $testCaseId = null;

    public string $name = '';

    #[On('delete-test-case')]
    public function load(string $testCaseId): void
    {
        $case = TestCase::find($testCaseId);

        abort_if($case === null, 404);

        $this->testCaseId = $testCaseId;
        $this->name = $case->name;

        $this->modal('delete-test-case')->show();
    }

    public function delete(): void
    {
        $case = TestCase::find($this->testCaseId);

        abort_if($case === null, 404);

        $case->delete();

        $this->modal('delete-test-case')->close();
        $this->reset(['testCaseId', 'name']);
        $this->dispatch('test-case-saved');
    }
}; ?>

<flux:modal name="delete-test-case" :show="$errors->isNotEmpty()" focusable class="max-w-md">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete this test case?') }}</flux:heading>
            @if ($name)
                <flux:subheading>{{ __('“:name” will be removed.', ['name' => $name]) }}</flux:subheading>
            @endif
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="danger">{{ __('Delete case') }}</flux:button>
        </div>
    </form>
</flux:modal>
