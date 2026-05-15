<?php

use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.auth')] #[Title('Accept invitation')] class extends Component
{
    public WorkspaceInvitation $invitation;

    public string $name = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public function mount(string $token): void
    {
        $invitation = WorkspaceInvitation::with('workspace', 'invitedBy')
            ->where('token', $token)
            ->first();

        abort_if($invitation === null, 404);
        abort_if(! $invitation->isPending(), 410, __('This invitation is no longer valid.'));

        $this->invitation = $invitation;

        $user = auth()->user();
        if ($user === null) {
            return;
        }

        if (strcasecmp($user->email, $invitation->email) !== 0) {
            abort(403, __('Sign in as :email to accept this invitation.', ['email' => $invitation->email]));
        }

        $this->acceptForExistingUser($user);
        $this->redirect('/dashboard', navigate: false);
    }

    public function signupAndAccept(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::default()],
        ]);

        if ($this->password !== $this->passwordConfirmation) {
            $this->addError('passwordConfirmation', __('The password confirmation does not match.'));

            return;
        }

        if (User::where('email', $this->invitation->email)->exists()) {
            $this->addError('email', __('An account with that email already exists. Please log in.'));

            return;
        }

        $user = DB::transaction(function () use ($validated): User {
            $user = User::withoutDefaultWorkspace(fn () => User::create([
                'name' => $validated['name'],
                'email' => $this->invitation->email,
                'password' => $this->password,
            ]));

            $this->attachMembership($user);
            $user->switchWorkspace($this->invitation->workspace_id);
            $this->invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });

        auth()->login($user);

        $this->redirect('/dashboard', navigate: false);
    }

    private function acceptForExistingUser(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->attachMembership($user);
            $user->switchWorkspace($this->invitation->workspace_id);
            $this->invitation->forceFill(['accepted_at' => now()])->save();
        });
    }

    private function attachMembership(User $user): void
    {
        WorkspaceMembership::firstOrCreate(
            [
                'workspace_id' => $this->invitation->workspace_id,
                'user_id' => $user->id,
            ],
            ['role' => $this->invitation->role],
        );
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Join :workspace', ['workspace' => $invitation->workspace->name])"
        :description="__('You\'ve been invited as a :role.', ['role' => ucfirst($invitation->role)])"
    />

    <flux:callout icon="envelope">
        {{ __('Invitation for') }}
        <span class="font-medium">{{ $invitation->email }}</span>
    </flux:callout>

    <form wire:submit="signupAndAccept" class="flex flex-col gap-6">
        <flux:input
            wire:model="name"
            :label="__('Your name')"
            type="text"
            required
            autofocus
            autocomplete="name"
        />

        <flux:input
            :value="$invitation->email"
            :label="__('Email')"
            type="email"
            readonly
            disabled
        />

        <flux:input
            wire:model="password"
            :label="__('Password')"
            type="password"
            required
            autocomplete="new-password"
            viewable
        />

        <flux:input
            wire:model="passwordConfirmation"
            :label="__('Confirm password')"
            type="password"
            required
            autocomplete="new-password"
        />

        <flux:button variant="primary" type="submit" class="w-full" data-test="accept-invitation-button">
            {{ __('Create account & accept') }}
        </flux:button>
    </form>

    <div class="text-sm text-center text-zinc-600 dark:text-zinc-400">
        {{ __('Already have an account?') }}
        <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
    </div>
</div>
