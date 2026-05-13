<?php

use App\Models\Concern;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $concernId = null;

    public string $preview = '';

    #[On('delete-concern')]
    public function load(string $concernId): void
    {
        $concern = Concern::find($concernId);

        abort_if($concern === null, 404);

        $this->concernId = $concernId;
        $this->preview = \Illuminate\Support\Str::limit($concern->text, 100);

        $this->modal('delete-concern')->show();
    }

    public function delete(): void
    {
        $concern = Concern::find($this->concernId);

        abort_if($concern === null, 404);

        $concern->delete();

        $this->modal('delete-concern')->close();
        $this->reset(['concernId', 'preview']);
        $this->dispatch('concern-saved');
    }
}; ?>

<flux:modal name="delete-concern" :show="$errors->isNotEmpty()" focusable class="max-w-md">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete this concern?') }}</flux:heading>
            @if ($preview)
                <flux:subheading>“{{ $preview }}”</flux:subheading>
            @endif
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="danger">{{ __('Delete concern') }}</flux:button>
        </div>
    </form>
</flux:modal>
