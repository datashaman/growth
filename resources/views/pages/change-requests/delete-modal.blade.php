<?php

use App\Models\ChangeRequest;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $changeRequestId = null;

    public string $title = '';
    public int $impactCount = 0;
    public int $approvalCount = 0;

    #[On('delete-change-request')]
    public function load(string $changeRequestId): void
    {
        $cr = ChangeRequest::query()
            ->withCount(['impacts', 'approvalEvents'])
            ->find($changeRequestId);

        abort_if($cr === null, 404);

        $this->changeRequestId = $changeRequestId;
        $this->title = $cr->title;
        $this->impactCount = (int) ($cr->impacts_count ?? 0);
        $this->approvalCount = (int) ($cr->approval_events_count ?? 0);

        $this->modal('delete-change-request')->show();
    }

    public function delete(): void
    {
        $cr = ChangeRequest::find($this->changeRequestId);

        abort_if($cr === null, 404);

        $cr->delete();

        $this->modal('delete-change-request')->close();
        $this->reset(['changeRequestId', 'title', 'impactCount', 'approvalCount']);
        $this->dispatch('change-request-saved');
    }
}; ?>

<flux:modal name="delete-change-request" :show="$errors->isNotEmpty()" focusable class="max-w-md">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete this change request?') }}</flux:heading>
            @if ($title)
                <flux:subheading>{{ __('“:title” will be removed.', ['title' => $title]) }}</flux:subheading>
            @endif
            @if ($impactCount > 0 || $approvalCount > 0)
                <flux:callout icon="exclamation-triangle" color="amber" class="mt-3">
                    <flux:callout.text>
                        {{ __('Will also remove') }}
                        @if ($impactCount > 0) {{ $impactCount }} {{ __('impacts') }} @endif
                        @if ($approvalCount > 0) {{ $approvalCount }} {{ __('approval events') }} @endif
                    </flux:callout.text>
                </flux:callout>
            @endif
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="danger">{{ __('Delete change') }}</flux:button>
        </div>
    </form>
</flux:modal>
