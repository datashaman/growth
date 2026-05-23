<?php

use App\Concerns\ProjectScoped;
use App\Models\Role;
use App\Models\WorkspaceMembership;
use App\Support\Capability;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Roles')] class extends Component {
    use ProjectScoped;

    /**
     * @return array<string,string>
     */
    public function getListeners(): array
    {
        return $this->projectScopedListeners();
    }

    public function onProjectDataChanged(): void
    {
        unset($this->roles);
    }

    #[Computed]
    public function roles()
    {
        return $this->selectedProject?->roles()->with(['users', 'capabilityAssignments'])->orderBy('name')->get() ?? collect();
    }

    #[Computed]
    public function canManageRoleCapabilities(): bool
    {
        if ($this->selectedProject === null || auth()->id() === null) {
            return false;
        }

        return WorkspaceMembership::query()
            ->where('workspace_id', $this->selectedProject->workspace_id)
            ->where('user_id', auth()->id())
            ->whereIn('role', [WorkspaceMembership::ROLE_OWNER, WorkspaceMembership::ROLE_ADMIN])
            ->exists();
    }

    /**
     * @return list<Capability>
     */
    public function capabilities(): array
    {
        return Capability::cases();
    }

    public function toggleRoleCapability(string $roleId, string $capability): void
    {
        abort_unless($this->canManageRoleCapabilities(), 403);

        $capability = Capability::tryFrom($capability);
        abort_unless($capability instanceof Capability, 422);

        $role = $this->selectedProject
            ?->roles()
            ->with('capabilityAssignments')
            ->findOrFail($roleId);

        /** @var Role $role */
        $current = $role->capabilities()->map->value;
        $next = $current->contains($capability->value)
            ? $current->reject(fn (string $value): bool => $value === $capability->value)
            : $current->push($capability->value);

        $role->syncCapabilities($next);

        unset($this->roles);

        $this->dispatch('role-capabilities-updated');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-project-page-header
        :title="__('Roles')"
        :description="__('Accountabilities, role holders, and the sections each role is responsible for.')" />

    @if ($this->selectedProject === null)
        <flux:callout icon="cursor-arrow-rays">
            <flux:callout.heading>{{ __('Select a project') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Pick a project to see its roles.') }}</flux:callout.text>
        </flux:callout>
    @else
        <x-data-table
            :title="__('Role assignments')"
            :count="$this->roles->count()"
            :count-label="__('defined')"
            :empty="$this->roles->isEmpty()"
            :empty-message="__('No roles defined.')">
            <flux:table class="[&_td]:align-top [&_table]:table-fixed [&_th:first-child]:w-[20%] [&_th:nth-child(2)]:w-[16%] [&_th:nth-child(3)]:w-[24%] [&_th:nth-child(4)]:w-[40%]">
                <flux:table.columns>
                    <flux:table.column>{{ __('Role') }}</flux:table.column>
                    <flux:table.column>{{ __('Users') }}</flux:table.column>
                    <flux:table.column>{{ __('Responsibilities') }}</flux:table.column>
                    <flux:table.column>{{ __('Sections') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->roles as $role)
                        <flux:table.row>
                            <flux:table.cell class="font-medium">
                                <div class="truncate whitespace-nowrap" title="{{ $role->name }}">{{ $role->name }}</div>
                            </flux:table.cell>
                            <flux:table.cell>
                                @php($users = $role->users->pluck('name')->join(', ') ?: '—')
                                <div class="truncate whitespace-nowrap" title="{{ $users }}">{{ $users }}</div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $role->responsibilities ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($this->canManageRoleCapabilities)
                                    <div class="grid gap-x-5 gap-y-2 xl:grid-cols-2" data-test="role-capabilities-{{ $role->id }}">
                                        @foreach ($this->capabilities() as $capability)
                                            @php($checked = $role->capabilities()->contains(fn (Capability $assigned): bool => $assigned === $capability))
                                            <label class="flex min-w-0 items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                                                <input
                                                    type="checkbox"
                                                    class="size-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-800"
                                                    @checked($checked)
                                                    wire:click="toggleRoleCapability('{{ $role->id }}', '{{ $capability->value }}')"
                                                    data-test="role-capability-{{ $role->id }}-{{ $capability->value }}"
                                                >
                                                <span class="min-w-0">{{ $capability->label() }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @elseif ($role->capabilities()->isNotEmpty())
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($role->capabilities() as $capability)
                                            <flux:badge size="sm">{{ $capability->label() }}</flux:badge>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-sm text-zinc-400 dark:text-zinc-500">—</span>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif
</div>
