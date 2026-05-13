<?php

use App\Models\Deployment;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $deploymentId = null;

    public string $environment = '';

    #[On('delete-deployment')]
    public function load(string $deploymentId): void
    {
        $deployment = Deployment::find($deploymentId);

        abort_if($deployment === null, 404);

        $this->deploymentId = $deploymentId;
        $this->environment = $deployment->environment;

        $this->modal('delete-deployment')->show();
    }

    public function delete(): void
    {
        $deployment = Deployment::find($this->deploymentId);

        abort_if($deployment === null, 404);

        $deployment->delete();

        $this->modal('delete-deployment')->close();
        $this->reset(['deploymentId', 'environment']);
        $this->dispatch('deployment-saved');
    }
}; ?>

<flux:modal name="delete-deployment" :show="$errors->isNotEmpty()" focusable class="max-w-md">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete this deployment?') }}</flux:heading>
            @if ($environment)
                <flux:subheading>{{ __('Deployment to “:env” will be removed.', ['env' => $environment]) }}</flux:subheading>
            @endif
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="danger">{{ __('Delete deployment') }}</flux:button>
        </div>
    </form>
</flux:modal>
