<?php

use App\Models\Release;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $releaseId = null;

    public string $version = '';
    public int $deploymentCount = 0;

    #[On('delete-release')]
    public function load(string $releaseId): void
    {
        $release = Release::query()
            ->withCount('deployments')
            ->find($releaseId);

        abort_if($release === null, 404);

        $this->releaseId = $releaseId;
        $this->version = $release->version;
        $this->deploymentCount = (int) ($release->deployments_count ?? 0);

        $this->modal('delete-release')->show();
    }

    public function delete(): void
    {
        $release = Release::find($this->releaseId);

        abort_if($release === null, 404);

        $release->delete();

        $this->modal('delete-release')->close();
        $this->reset(['releaseId', 'version', 'deploymentCount']);
        $this->dispatch('release-saved');
    }
}; ?>

<flux:modal name="delete-release" :show="$errors->isNotEmpty()" focusable class="max-w-md">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete this release?') }}</flux:heading>
            @if ($version)
                <flux:subheading>{{ __('Release :version will be removed.', ['version' => $version]) }}</flux:subheading>
            @endif
            @if ($deploymentCount > 0)
                <flux:callout icon="exclamation-triangle" color="amber" class="mt-3">
                    <flux:callout.text>{{ __(':count deployments will lose their release reference.', ['count' => $deploymentCount]) }}</flux:callout.text>
                </flux:callout>
            @endif
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="danger">{{ __('Delete release') }}</flux:button>
        </div>
    </form>
</flux:modal>
