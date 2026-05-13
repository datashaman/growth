<?php

use App\Models\Role;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $roleId = null;

    public string $name = '';
    public int $workItemCount = 0;
    public int $riskCount = 0;
    public int $reviewCount = 0;

    #[On('delete-role')]
    public function load(string $roleId): void
    {
        $role = Role::query()
            ->withCount(['workItems', 'risks', 'reviews'])
            ->find($roleId);

        abort_if($role === null, 404);

        $this->roleId = $roleId;
        $this->name = $role->name;
        $this->workItemCount = (int) ($role->work_items_count ?? 0);
        $this->riskCount = (int) ($role->risks_count ?? 0);
        $this->reviewCount = (int) ($role->reviews_count ?? 0);

        $this->modal('delete-role')->show();
    }

    public function delete(): void
    {
        $role = Role::find($this->roleId);

        abort_if($role === null, 404);

        $role->delete();

        $this->modal('delete-role')->close();
        $this->reset(['roleId', 'name', 'workItemCount', 'riskCount', 'reviewCount']);
        $this->dispatch('role-saved');
    }
}; ?>

<flux:modal name="delete-role" :show="$errors->isNotEmpty()" focusable class="max-w-md">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete this role?') }}</flux:heading>
            @if ($name)
                <flux:subheading>{{ __('“:name” will be removed. References on work items, risks, and reviews will be cleared.', ['name' => $name]) }}</flux:subheading>
            @endif
            @if ($workItemCount > 0 || $riskCount > 0 || $reviewCount > 0)
                <flux:callout icon="exclamation-triangle" color="amber" class="mt-3">
                    <flux:callout.text>
                        {{ __('Used by') }}
                        @if ($workItemCount > 0) {{ $workItemCount }} {{ __('work items') }} @endif
                        @if ($riskCount > 0) {{ $riskCount }} {{ __('risks') }} @endif
                        @if ($reviewCount > 0) {{ $reviewCount }} {{ __('reviews') }} @endif
                    </flux:callout.text>
                </flux:callout>
            @endif
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="danger">{{ __('Delete role') }}</flux:button>
        </div>
    </form>
</flux:modal>
