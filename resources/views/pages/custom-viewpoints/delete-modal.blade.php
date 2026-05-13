<?php

use App\Models\CustomViewpoint;
use App\Models\DesignView;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $customViewpointId = null;

    public string $name = '';
    public int $usageCount = 0;

    #[On('delete-custom-viewpoint')]
    public function load(string $customViewpointId): void
    {
        $viewpoint = CustomViewpoint::find($customViewpointId);

        abort_if($viewpoint === null, 404);

        $this->customViewpointId = $customViewpointId;
        $this->name = $viewpoint->name;
        $this->usageCount = DesignView::query()
            ->where('project_id', $viewpoint->project_id)
            ->where('viewpoint', $viewpoint->name)
            ->count();

        $this->modal('delete-custom-viewpoint')->show();
    }

    public function delete(): void
    {
        $viewpoint = CustomViewpoint::find($this->customViewpointId);

        abort_if($viewpoint === null, 404);

        $viewpoint->delete();

        $this->modal('delete-custom-viewpoint')->close();
        $this->reset(['customViewpointId', 'name', 'usageCount']);
        $this->dispatch('custom-viewpoint-saved');
    }
}; ?>

<flux:modal name="delete-custom-viewpoint" :show="$errors->isNotEmpty()" focusable class="max-w-md">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete this custom viewpoint?') }}</flux:heading>
            @if ($name)
                <flux:subheading>{{ __('“:name” will be removed. Design views already using it will keep their viewpoint string but no longer validate against this rule.', ['name' => $name]) }}</flux:subheading>
            @endif
            @if ($usageCount > 0)
                <flux:callout icon="exclamation-triangle" color="amber" class="mt-3">
                    <flux:callout.text>{{ __('Used by :n design views.', ['n' => $usageCount]) }}</flux:callout.text>
                </flux:callout>
            @endif
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="danger">{{ __('Delete viewpoint') }}</flux:button>
        </div>
    </form>
</flux:modal>
