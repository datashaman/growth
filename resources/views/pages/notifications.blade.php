<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Notifications')] class extends Component {
    /**
     * Live arrival: refresh when a notification broadcasts to this user.
     *
     * @return array<string,string>
     */
    public function getListeners(): array
    {
        return [
            'echo-private:App.Models.User.'.auth()->id().',.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated' => 'onNotificationReceived',
        ];
    }

    public function onNotificationReceived(): void
    {
        unset($this->items, $this->threads);
    }

    /**
     * Mark every unread message in a thread read, then a refresh. The key is
     * a thread id for a real thread, or a lone notification's own id when it
     * has no thread.
     */
    public function markThreadRead(string $key): void
    {
        $this->scopedQuery()
            ->whereNull('read_at')
            ->where(function ($query) use ($key): void {
                $query->where('data->thread_id', $key)->orWhere('id', $key);
            })
            ->update(['read_at' => now()]);

        unset($this->items, $this->threads);
    }

    /**
     * Mark every unread notification in the active workspace read.
     */
    public function markAllRead(): void
    {
        $this->scopedQuery()->whereNull('read_at')->update(['read_at' => now()]);
        unset($this->items, $this->threads);
    }

    /**
     * The caller's notifications in the active workspace.
     */
    private function scopedQuery()
    {
        $workspaceId = auth()->user()?->active_workspace_id;

        return auth()->user()->notifications()
            ->where('data->workspace_id', $workspaceId);
    }

    #[Computed]
    public function items()
    {
        if (auth()->user()?->active_workspace_id === null) {
            return collect();
        }

        return $this->scopedQuery()
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }

    /**
     * Notifications grouped into threads — each thread is its messages in
     * arrival order, and threads are ordered by their most recent message.
     * A notification with no thread id stands as a thread of one.
     *
     * @return Collection<int,Collection<int,object>>
     */
    #[Computed]
    public function threads(): Collection
    {
        return $this->items
            ->groupBy(fn (object $note): string => $note->data['thread_id'] ?? $note->id)
            ->map(fn (Collection $messages): Collection => $messages->sortBy('created_at')->values())
            ->sortByDesc(fn (Collection $messages): mixed => $messages->last()->created_at)
            ->values();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-project-page-header
        :title="__('Notifications')"
        :description="__('Notifications addressed to you in this workspace, grouped by thread.')">
        <x-slot:actions>
            <flux:button wire:click="markAllRead" size="sm" variant="subtle">{{ __('Mark all read') }}</flux:button>
        </x-slot:actions>
    </x-project-page-header>

    <x-data-table
        :count="$this->threads->count()"
        :count-label="__('threads')"
        :empty="$this->threads->isEmpty()"
        :empty-message="__('No notifications yet.')">
        <flux:table class="[&_td]:align-top">
            <flux:table.columns>
                <flux:table.column>{{ __('When') }}</flux:table.column>
                <flux:table.column>{{ __('Thread') }}</flux:table.column>
                <flux:table.column>{{ __('From') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->threads as $thread)
                    @php
                        $latest = $thread->last();
                        $unread = $thread->whereNull('read_at');
                        $threadKey = $thread->first()->data['thread_id'] ?? $thread->first()->id;
                    @endphp
                    <flux:table.row :class="$unread->isNotEmpty() ? 'font-medium' : 'text-zinc-500 dark:text-zinc-400'">
                        <flux:table.cell class="whitespace-nowrap">
                            <span title="{{ $latest->created_at?->toIso8601String() }}">{{ $latest->created_at?->diffForHumans() ?? '—' }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($thread->count() > 1)
                                <div class="mb-1 text-xs uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                                    {{ trans_choice('1 message|:count messages', $thread->count()) }}
                                </div>
                            @endif
                            <div class="flex flex-col gap-2">
                                @foreach ($thread as $message)
                                    <div @class(['border-s-2 ps-2', 'border-green-500' => $message->read_at === null, 'border-transparent' => $message->read_at !== null])>
                                        @if ($message->data['url'] ?? null)
                                            <a href="{{ $message->data['url'] }}" wire:navigate class="hover:underline">{{ $message->data['title'] ?? __('Notification') }}</a>
                                        @else
                                            {{ $message->data['title'] ?? __('Notification') }}
                                        @endif
                                        <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $message->data['body'] ?? '' }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap text-sm">
                            {{ $latest->data['sender']['name'] ?? __('System') }}
                            @if ($latest->data['acting_role'] ?? null)
                                <span class="text-zinc-400 dark:text-zinc-500">· {{ $latest->data['acting_role'] }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap text-right">
                            @if ($unread->isNotEmpty())
                                <flux:button wire:click="markThreadRead('{{ $threadKey }}')" size="xs" variant="subtle">{{ __('Mark read') }}</flux:button>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('read') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </x-data-table>
</div>
