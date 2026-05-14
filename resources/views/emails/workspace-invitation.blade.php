<x-mail::message>
# {{ __('You\'re invited to :workspace', ['workspace' => $workspace->name]) }}

@if ($inviter)
{{ __(':name has invited you to join the :workspace workspace as a :role.', [
    'name' => $inviter->name,
    'workspace' => $workspace->name,
    'role' => $invitation->role,
]) }}
@else
{{ __('You have been invited to join the :workspace workspace as a :role.', [
    'workspace' => $workspace->name,
    'role' => $invitation->role,
]) }}
@endif

<x-mail::button :url="$acceptUrl">
{{ __('Accept invitation') }}
</x-mail::button>

{{ __('This invitation expires :date.', ['date' => $expiresAt->toFormattedDateString()]) }}

{{ __('If you didn\'t expect this, you can ignore the email.') }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
