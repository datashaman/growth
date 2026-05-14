<?php

use App\Mail\WorkspaceInvitationMail;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;
use App\Support\WorkspaceContext;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Workspace settings')] class extends Component
{
    public string $name = '';

    public ?string $workspaceId = null;

    public string $inviteEmail = '';

    public string $inviteRole = WorkspaceMembership::ROLE_MEMBER;

    public string $newWorkspaceName = '';

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

    #[Computed]
    public function pendingInvitations(): Collection
    {
        return WorkspaceInvitation::query()
            ->where('workspace_id', $this->workspaceId)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->with('invitedBy:id,name')
            ->orderByDesc('id')
            ->get();
    }

    public function sendInvitation(): void
    {
        abort_unless($this->canMutate(), 403);

        $rules = [
            'inviteEmail' => ['required', 'email', 'max:255'],
            'inviteRole' => ['required', 'in:'.implode(',', WorkspaceMembership::ROLES)],
        ];

        $this->validate($rules);

        if (
            $this->inviteRole === WorkspaceMembership::ROLE_OWNER
            && $this->viewerRole !== WorkspaceMembership::ROLE_OWNER
        ) {
            $this->addError('inviteRole', __('Only owners can invite as owner.'));

            return;
        }

        $existingMember = WorkspaceMembership::query()
            ->where('workspace_id', $this->workspaceId)
            ->whereHas('user', fn ($q) => $q->where('email', $this->inviteEmail))
            ->exists();

        if ($existingMember) {
            $this->addError('inviteEmail', __('Already a member.'));

            return;
        }

        $invitation = WorkspaceInvitation::query()
            ->where('workspace_id', $this->workspaceId)
            ->where('email', $this->inviteEmail)
            ->whereNull('accepted_at')
            ->first();

        if ($invitation) {
            $invitation->forceFill([
                'role' => $this->inviteRole,
                'token' => WorkspaceInvitation::generateToken(),
                'invited_by_user_id' => auth()->id(),
                'expires_at' => WorkspaceInvitation::defaultExpiry(),
            ])->save();
        } else {
            $invitation = WorkspaceInvitation::create([
                'workspace_id' => $this->workspaceId,
                'email' => $this->inviteEmail,
                'role' => $this->inviteRole,
                'token' => WorkspaceInvitation::generateToken(),
                'invited_by_user_id' => auth()->id(),
                'expires_at' => WorkspaceInvitation::defaultExpiry(),
            ]);
        }

        Mail::to($invitation->email)->send(new WorkspaceInvitationMail($invitation->load('workspace', 'invitedBy')));

        $this->reset(['inviteEmail']);
        $this->inviteRole = WorkspaceMembership::ROLE_MEMBER;
        unset($this->pendingInvitations);

        Flux::toast(variant: 'success', text: __('Invitation sent.'));
    }

    public function resendInvitation(int $invitationId): void
    {
        abort_unless($this->canMutate(), 403);

        $invitation = WorkspaceInvitation::query()
            ->where('workspace_id', $this->workspaceId)
            ->whereNull('accepted_at')
            ->whereKey($invitationId)
            ->firstOrFail();

        $invitation->forceFill([
            'token' => WorkspaceInvitation::generateToken(),
            'expires_at' => WorkspaceInvitation::defaultExpiry(),
        ])->save();

        Mail::to($invitation->email)->send(new WorkspaceInvitationMail($invitation->load('workspace', 'invitedBy')));

        unset($this->pendingInvitations);

        Flux::toast(variant: 'success', text: __('Invitation resent.'));
    }

    public function cancelInvitation(int $invitationId): void
    {
        abort_unless($this->canMutate(), 403);

        WorkspaceInvitation::query()
            ->where('workspace_id', $this->workspaceId)
            ->whereKey($invitationId)
            ->delete();

        unset($this->pendingInvitations);

        Flux::toast(variant: 'success', text: __('Invitation cancelled.'));
    }

    public function createWorkspace(): void
    {
        $validated = $this->validate([
            'newWorkspaceName' => ['required', 'string', 'max:255'],
        ]);

        $user = auth()->user();

        $workspace = Workspace::create([
            'name' => $validated['newWorkspaceName'],
            'slug' => Workspace::uniqueSlug($validated['newWorkspaceName']),
            'owner_user_id' => $user->id,
        ]);

        WorkspaceMembership::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMembership::ROLE_OWNER,
        ]);

        $user->forceFill(['active_workspace_id' => $workspace->id])->save();
        app(WorkspaceContext::class)->forget();

        $this->redirect(route('workspace.edit'), navigate: false);
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

        @if ($this->canMutate())
            <flux:separator class="my-8" />

            <flux:heading size="lg" class="mb-3">{{ __('Invite a teammate') }}</flux:heading>
            <form wire:submit="sendInvitation" class="space-y-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-end">
                    <div class="flex-1">
                        <flux:input
                            wire:model="inviteEmail"
                            :label="__('Email')"
                            type="email"
                            required
                            data-test="invite-email"
                        />
                    </div>
                    <div class="md:w-40">
                        <flux:select wire:model="inviteRole" :label="__('Role')" data-test="invite-role">
                            @foreach (\App\Models\WorkspaceMembership::ROLES as $role)
                                @if ($role === \App\Models\WorkspaceMembership::ROLE_OWNER && $this->viewerRole !== \App\Models\WorkspaceMembership::ROLE_OWNER)
                                    @continue
                                @endif
                                <flux:select.option value="{{ $role }}">{{ ucfirst($role) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:button variant="primary" type="submit" data-test="send-invitation-button">
                        {{ __('Send invitation') }}
                    </flux:button>
                </div>
            </form>

            <div class="mt-6">
                <x-data-table :title="__('Pending invitations')" :count="$this->pendingInvitations->count()" :empty="$this->pendingInvitations->isEmpty()" :empty-message="__('No pending invitations.')">
                    <table class="w-full table-auto text-sm">
                        <thead class="text-left text-xs uppercase text-zinc-500 dark:text-zinc-400">
                            <tr>
                                <th class="py-2 pr-3">{{ __('Email') }}</th>
                                <th class="py-2 pr-3">{{ __('Role') }}</th>
                                <th class="py-2 pr-3">{{ __('Expires') }}</th>
                                <th class="py-2 text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->pendingInvitations as $invitation)
                                <tr wire:key="invitation-{{ $invitation->id }}">
                                    <td class="py-2 pr-3 font-medium">{{ $invitation->email }}</td>
                                    <td class="py-2 pr-3">
                                        <flux:badge :color="\App\Support\BadgeVariant::workspaceRole($invitation->role)" size="sm">{{ ucfirst($invitation->role) }}</flux:badge>
                                    </td>
                                    <td class="py-2 pr-3 text-xs text-zinc-500 dark:text-zinc-400">{{ $invitation->expires_at->diffForHumans() }}</td>
                                    <td class="py-2 text-right">
                                        <flux:button size="xs" variant="ghost" wire:click="resendInvitation({{ $invitation->id }})" data-test="resend-{{ $invitation->id }}">
                                            {{ __('Resend') }}
                                        </flux:button>
                                        <flux:button size="xs" variant="ghost" icon="trash" wire:click="cancelInvitation({{ $invitation->id }})" data-test="cancel-{{ $invitation->id }}" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-data-table>
            </div>
        @endif

        <flux:separator class="my-8" />

        <flux:heading size="lg" class="mb-3">{{ __('Create a new workspace') }}</flux:heading>
        <flux:subheading class="mb-4">{{ __('Workspaces let you keep separate projects and teams.') }}</flux:subheading>

        <flux:modal.trigger name="create-workspace">
            <flux:button variant="primary" data-test="create-workspace-button">{{ __('New workspace') }}</flux:button>
        </flux:modal.trigger>

        <flux:modal name="create-workspace" class="md:w-96">
            <form wire:submit="createWorkspace" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('New workspace') }}</flux:heading>
                    <flux:subheading>{{ __('Give your workspace a name. You can change it later.') }}</flux:subheading>
                </div>

                <flux:input
                    wire:model="newWorkspaceName"
                    :label="__('Name')"
                    type="text"
                    required
                    data-test="new-workspace-name"
                />

                <div class="flex justify-end">
                    <flux:button variant="primary" type="submit" data-test="confirm-new-workspace">
                        {{ __('Create') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    </x-pages::settings.layout>
</section>
