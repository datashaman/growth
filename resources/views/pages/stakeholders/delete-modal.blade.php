<?php

use App\Models\Stakeholder;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $stakeholderId = null;

    public string $name = '';
    public int $concernCount = 0;

    #[On('delete-stakeholder')]
    public function load(string $stakeholderId): void
    {
        $stakeholder = Stakeholder::query()
            ->withCount('concerns')
            ->find($stakeholderId);

        abort_if($stakeholder === null, 404);

        $this->stakeholderId = $stakeholderId;
        $this->name = $stakeholder->name;
        $this->concernCount = (int) ($stakeholder->concerns_count ?? 0);

        $this->modal('delete-stakeholder')->show();
    }

    public function delete(): void
    {
        $stakeholder = Stakeholder::find($this->stakeholderId);

        abort_if($stakeholder === null, 404);

        $stakeholder->delete();

        $this->modal('delete-stakeholder')->close();
        $this->reset(['stakeholderId', 'name', 'concernCount']);
        $this->dispatch('stakeholder-saved');
    }
}; ?>

<flux:modal name="delete-stakeholder" :show="$errors->isNotEmpty()" focusable class="max-w-md">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete this stakeholder?') }}</flux:heading>
            @if ($name)
                <flux:subheading>{{ __('“:name” will be removed.', ['name' => $name]) }}</flux:subheading>
            @endif
            @if ($concernCount > 0)
                <flux:callout icon="exclamation-triangle" color="amber" class="mt-3">
                    <flux:callout.text>{{ __(':count concerns will lose their raised-by reference.', ['count' => $concernCount]) }}</flux:callout.text>
                </flux:callout>
            @endif
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="danger">{{ __('Delete stakeholder') }}</flux:button>
        </div>
    </form>
</flux:modal>
