<?php

use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /**
     * Display context: 'sidebar' is the full-width labelled button; 'bar' is
     * the compact icon trigger used in the desktop top bar.
     */
    public string $variant = 'sidebar';

    public function mount(string $variant = 'sidebar'): void
    {
        $this->variant = $variant;
    }

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
     * The caller's notifications scoped to the active workspace.
     */
    private function scopedQuery()
    {
        return auth()->user()->notifications()
            ->where('data->workspace_id', auth()->user()?->active_workspace_id);
    }

    /**
     * The most recent notifications, newest first.
     */
    #[Computed]
    public function items()
    {
        return $this->scopedQuery()->latest()->limit(20)->get();
    }

    #[Computed]
    public function unreadCount(): int
    {
        return $this->scopedQuery()->whereNull('read_at')->count();
    }

    public function refreshInbox(): void
    {
        unset($this->items, $this->unreadCount);
    }

    public function markAsRead(string $id): void
    {
        $this->scopedQuery()
            ->whereKey($id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $this->refreshInbox();
    }

    public function markAllAsRead(): void
    {
        $this->scopedQuery()->whereNull('read_at')->update(['read_at' => now()]);

        $this->refreshInbox();
    }
}; ?>

<div x-data="{ open: false }" @keydown.escape.window="open = false">
    @if ($variant === 'bar')
        <button
            type="button"
            @click="open = true"
            aria-label="{{ __('Notifications') }}"
            class="relative flex items-center justify-center rounded-lg p-1.5 text-zinc-500 transition hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800"
        >
            <flux:icon.bell class="size-5" />
            @if ($this->unreadCount > 0)
                <span
                    data-test="notification-indicator"
                    class="absolute -end-0.5 -top-0.5 flex min-w-4 items-center justify-center rounded-full bg-green-500 px-1 text-[10px] font-medium leading-none text-white ring-2 ring-white dark:ring-zinc-900"
                >
                    {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
                </span>
            @endif
        </button>
    @else
        <button
            type="button"
            @click="open = true"
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
    @endif

    {{-- Backdrop --}}
    <div
        x-show="open"
        x-cloak
        x-transition.opacity.duration.200ms
        @click="open = false"
        class="fixed inset-0 z-40 bg-zinc-900/50"
    ></div>

    {{-- Slide-out notification drawer, anchored to the right edge --}}
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        role="dialog"
        aria-modal="true"
        aria-label="{{ __('Notifications') }}"
        class="fixed inset-y-0 end-0 z-50 flex w-96 max-w-[90vw] flex-col border-s border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-800"
    >
        <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Notifications') }}</span>
            <div class="flex items-center gap-3">
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
                <button
                    type="button"
                    @click="open = false"
                    aria-label="{{ __('Close') }}"
                    class="text-zinc-400 transition hover:text-zinc-700 dark:hover:text-zinc-200"
                >
                    <flux:icon.x-mark class="size-4" />
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto">
            @forelse ($this->items as $note)
                <div
                    @class([
                        'flex items-start gap-2 border-b border-zinc-100 px-4 py-3 last:border-b-0 dark:border-zinc-700/60',
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
                <div class="px-4 py-12 text-center text-sm text-zinc-400">
                    {{ __('No notifications.') }}
                </div>
            @endforelse
        </div>

        <div class="border-t border-zinc-200 px-4 py-3 text-center dark:border-zinc-700">
            <a
                href="{{ route('notifications') }}"
                wire:navigate
                @click="open = false"
                data-test="notifications-view-all"
                class="text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-zinc-100"
            >
                {{ __('View all notifications') }}
            </a>
        </div>
    </div>
</div>
