<?php

use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Workspace settings')] class extends Component
{
    public string $name = '';

    public ?string $workspaceId = null;

    public function mount(): void
    {
        $workspace = $this->workspace();

        $this->workspaceId = $workspace->id;
        $this->name = $workspace->name;
    }

    public function workspace(): Workspace
    {
        return auth()->user()->activeWorkspace;
    }

    #[Computed]
    public function viewerRole(): string
    {
        return WorkspaceMembership::where('workspace_id', $this->workspaceId)
            ->where('user_id', auth()->id())
            ->value('role') ?? WorkspaceMembership::ROLE_VIEWER;
    }

    public function canMutate(): bool
    {
        return in_array($this->viewerRole, [
            WorkspaceMembership::ROLE_OWNER,
            WorkspaceMembership::ROLE_ADMIN,
        ], true);
    }

    #[Computed]
    public function memberships(): Collection
    {
        return WorkspaceMembership::query()
            ->where('workspace_id', $this->workspaceId)
            ->with('user:id,name,email')
            ->orderByRaw("CASE role WHEN 'owner' THEN 0 WHEN 'admin' THEN 1 WHEN 'member' THEN 2 WHEN 'viewer' THEN 3 ELSE 9 END")
            ->orderBy('id')
            ->get();
    }

    public function saveName(): void
    {
        if (! $this->canMutate()) {
            abort(403);
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        Workspace::whereKey($this->workspaceId)->update(['name' => $validated['name']]);

        Flux::toast(variant: 'success', text: __('Workspace updated.'));
    }

    public function setRole(int $membershipId, string $role): void
    {
        abort_unless($this->canMutate(), 403);
        abort_unless(in_array($role, WorkspaceMembership::ROLES, true), 422);

        $target = WorkspaceMembership::where('workspace_id', $this->workspaceId)
            ->whereKey($membershipId)
            ->firstOrFail();

        $touchesOwner = $target->role === WorkspaceMembership::ROLE_OWNER
            || $role === WorkspaceMembership::ROLE_OWNER;

        if ($touchesOwner && $this->viewerRole !== WorkspaceMembership::ROLE_OWNER) {
            abort(403);
        }

        if (
            $target->role === WorkspaceMembership::ROLE_OWNER
            && $role !== WorkspaceMembership::ROLE_OWNER
            && $this->ownerCount() <= 1
        ) {
            Flux::toast(variant: 'danger', text: __('A workspace must have at least one owner.'));

            return;
        }

        $target->update(['role' => $role]);
        unset($this->memberships);

        Flux::toast(variant: 'success', text: __('Role updated.'));
    }

    public function remove(int $membershipId): void
    {
        abort_unless($this->canMutate(), 403);

        $target = WorkspaceMembership::where('workspace_id', $this->workspaceId)
            ->whereKey($membershipId)
            ->firstOrFail();

        if ($target->role === WorkspaceMembership::ROLE_OWNER && $this->viewerRole !== WorkspaceMembership::ROLE_OWNER) {
            abort(403);
        }

        if ($target->role === WorkspaceMembership::ROLE_OWNER && $this->ownerCount() <= 1) {
            Flux::toast(variant: 'danger', text: __('Cannot remove the last owner.'));

            return;
        }

        $target->delete();
        unset($this->memberships);

        Flux::toast(variant: 'success', text: __('Member removed.'));
    }

    private function ownerCount(): int
    {
        return WorkspaceMembership::where('workspace_id', $this->workspaceId)
            ->where('role', WorkspaceMembership::ROLE_OWNER)
            ->count();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Workspace settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Workspace')" :subheading="__('Workspace name and members')">
        <form wire:submit="saveName" class="my-6 w-full space-y-6">
            <flux:input
                wire:model="name"
                :label="__('Name')"
                type="text"
                required
                :disabled="! $this->canMutate()"
                data-test="workspace-name"
            />

            @if ($this->canMutate())
                <flux:button variant="primary" type="submit" data-test="save-workspace-button">
                    {{ __('Save') }}
                </flux:button>
            @endif
        </form>

        <flux:separator class="my-8" />

        <x-data-table :title="__('Members')" :count="$this->memberships->count()" :empty="$this->memberships->isEmpty()" :empty-message="__('No members.')">
            <table class="w-full table-auto text-sm">
                <thead class="text-left text-xs uppercase text-zinc-500 dark:text-zinc-400">
                    <tr>
                        <th class="py-2 pr-3">{{ __('Member') }}</th>
                        <th class="py-2 pr-3">{{ __('Role') }}</th>
                        @if ($this->canMutate())
                            <th class="py-2"></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->memberships as $membership)
                        @php($isSelf = $membership->user_id === auth()->id())
                        <tr wire:key="membership-{{ $membership->id }}">
                            <td class="py-2 pr-3">
                                <div class="font-medium">{{ $membership->user->name }}@if ($isSelf) <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">({{ __('you') }})</flux:text>@endif</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $membership->user->email }}</div>
                            </td>
                            <td class="py-2 pr-3">
                                @if ($this->canMutate())
                                    <flux:select size="sm" :value="$membership->role" wire:change="setRole({{ $membership->id }}, $event.target.value)" data-test="role-{{ $membership->user_id }}">
                                        @foreach (\App\Models\WorkspaceMembership::ROLES as $role)
                                            <flux:select.option value="{{ $role }}">{{ ucfirst($role) }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @else
                                    <flux:badge :color="\App\Support\BadgeVariant::workspaceRole($membership->role)" size="sm">{{ ucfirst($membership->role) }}</flux:badge>
                                @endif
                            </td>
                            @if ($this->canMutate())
                                <td class="py-2 text-right">
                                    <flux:button
                                        size="xs"
                                        variant="ghost"
                                        icon="trash"
                                        wire:click="remove({{ $membership->id }})"
                                        data-test="remove-{{ $membership->user_id }}"
                                    />
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-data-table>

        @unless ($this->canMutate())
            <flux:text class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Only owners and admins can edit members.') }}</flux:text>
        @endunless
    </x-pages::settings.layout>
</section>
