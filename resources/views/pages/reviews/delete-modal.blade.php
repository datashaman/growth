<?php

use App\Models\Review;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public string $reviewId;

    public function mount(string $reviewId): void
    {
        $this->reviewId = $reviewId;
    }

    public function delete(): void
    {
        $review = Review::find($this->reviewId);

        abort_if($review === null, 404);

        $review->delete();

        $this->redirectRoute('dashboard', navigate: true);
    }
}; ?>

<flux:modal name="delete-review" :show="$errors->isNotEmpty()" focusable class="max-w-md">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete this review?') }}</flux:heading>
            <flux:subheading>{{ __('Findings, participants, and decision events will also be removed.') }}</flux:subheading>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="danger">{{ __('Delete review') }}</flux:button>
        </div>
    </form>
</flux:modal>
