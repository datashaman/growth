<?php

use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /**
     * Live arrival: refresh when a notification broadcasts to this user.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return [
            'echo-private:App.Models.User.'.auth()->id().',.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated' => 'refreshInbox',
        ];
    }

    /**
     * The most recent notifications, newest first.
     */
    #[Computed]
    public function items()
    {
        return auth()->user()->notifications()->latest()->limit(20)->get();
    }

    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    public function refreshInbox(): void
    {
        unset($this->items, $this->unreadCount);
    }

    public function markAsRead(string $id): void
    {
        auth()->user()->notifications()
            ->whereKey($id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $this->refreshInbox();
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);

        $this->refreshInbox();
    }
}; ?>

<div x-data="{ open: false }" class="relative" @keydown.escape.window="open = false">
    <button
        type="button"
        @click="open = ! open"
        class="flex w-full items-center gap-2 rounded-lg border border-zinc-200 px-3 py-1.5 text-sm text-zinc-500 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800"
    >
        <span class="relative shrink-0">
            <flux:icon.bell class="size-4" />
            @if ($this->unreadCount > 0)
                <span
                    data-test="notification-indicator"
                    class="absolute -end-1 -top-1 size-2 rounded-full bg-green-500 ring-2 ring-zinc-50 dark:ring-zinc-900"
                ></span>
            @endif
        </span>
        <span class="flex-1 text-start">{{ __('Notifications') }}</span>
        @if ($this->unreadCount > 0)
            <span class="rounded-full bg-green-500/15 px-1.5 text-xs font-medium text-green-600 dark:text-green-400">
                {{ $this->unreadCount }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        x-cloak
        @click.outside="open = false"
        class="absolute z-50 mt-1 max-h-[60vh] w-80 overflow-y-auto rounded-xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-800"
    >
        <div class="flex items-center justify-between border-b border-zinc-200 px-3 py-2 dark:border-zinc-700">
            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Notifications') }}</span>
            @if ($this->unreadCount > 0)
                <button
                    type="button"
                    wire:click="markAllAsRead"
                    data-test="mark-all-read"
                    class="text-xs text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-100"
                >
                    {{ __('Mark all as read') }}
                </button>
            @endif
        </div>

        @forelse ($this->items as $note)
            <div
                @class([
                    'flex items-start gap-2 border-b border-zinc-100 px-3 py-2 last:border-b-0 dark:border-zinc-700/60',
                    'bg-green-500/5' => $note->read_at === null,
                ])
            >
                <span class="mt-1.5 size-1.5 shrink-0 rounded-full {{ $note->read_at === null ? 'bg-green-500' : 'bg-transparent' }}"></span>

                <div class="min-w-0 flex-1">
                    @if ($note->data['url'] ?? null)
                        <a href="{{ $note->data['url'] }}" wire:navigate class="block text-sm font-medium text-zinc-800 hover:underline dark:text-zinc-100">
                            {{ $note->data['title'] ?? __('Notification') }}
                        </a>
                    @else
                        <span class="block text-sm font-medium text-zinc-800 dark:text-zinc-100">
                            {{ $note->data['title'] ?? __('Notification') }}
                        </span>
                    @endif
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $note->data['body'] ?? '' }}</p>
                    <p class="mt-0.5 text-xs text-zinc-400 dark:text-zinc-500">{{ $note->created_at->diffForHumans() }}</p>
                </div>

                @if ($note->read_at === null)
                    <button
                        type="button"
                        wire:click="markAsRead('{{ $note->id }}')"
                        data-test="mark-read"
                        class="shrink-0 text-xs text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200"
                    >
                        {{ __('Mark read') }}
                    </button>
                @endif
            </div>
        @empty
            <div class="px-4 py-8 text-center text-sm text-zinc-400">
                {{ __('No notifications.') }}
            </div>
        @endforelse
    </div>
</div>
