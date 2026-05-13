<?php

use App\Models\DesignView;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $designViewId = null;

    public string $name = '';
    public int $elementCount = 0;

    #[On('delete-design-view')]
    public function load(string $designViewId): void
    {
        $view = DesignView::query()
            ->withCount('elements')
            ->find($designViewId);

        abort_if($view === null, 404);

        $this->designViewId = $designViewId;
        $this->name = $view->name;
        $this->elementCount = (int) ($view->elements_count ?? 0);

        $this->modal('delete-design-view')->show();
    }

    public function delete(): void
    {
        $view = DesignView::find($this->designViewId);

        abort_if($view === null, 404);

        $view->delete();

        $this->modal('delete-design-view')->close();
        $this->reset(['designViewId', 'name', 'elementCount']);
        $this->dispatch('design-view-saved');
    }
}; ?>

<flux:modal name="delete-design-view" :show="$errors->isNotEmpty()" focusable class="max-w-md">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete this design view?') }}</flux:heading>
            @if ($name)
                <flux:subheading>{{ __('“:name” will be removed.', ['name' => $name]) }}</flux:subheading>
            @endif
            @if ($elementCount > 0)
                <flux:callout icon="exclamation-triangle" color="amber" class="mt-3">
                    <flux:callout.text>{{ __(':count elements will be deleted with the view.', ['count' => $elementCount]) }}</flux:callout.text>
                </flux:callout>
            @endif
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="danger">{{ __('Delete view') }}</flux:button>
        </div>
    </form>
</flux:modal>
