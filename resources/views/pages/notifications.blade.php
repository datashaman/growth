<?php

use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Notifications')] class extends Component {
    /**
     * @return array<string,string>
     */
    public function getListeners(): array
    {
        $workspaceId = auth()->user()?->active_workspace_id;

        if ($workspaceId === null) {
            return [];
        }

        return [
            'echo-private:workspaces.'.$workspaceId.',WorkspaceDataChanged' => 'onWorkspaceDataChanged',
        ];
    }

    public function onWorkspaceDataChanged(): void
    {
        unset($this->items);
    }

    /**
     * Mark a single notification read, then a refresh.
     */
    public function markRead(string $id): void
    {
        $this->scopedQuery()->whereKey($id)->update(['read_at' => now()]);
        unset($this->items);
    }

    /**
     * Mark every unread notification in the active workspace read.
     */
    public function markAllRead(): void
    {
        $this->scopedQuery()->whereNull('read_at')->update(['read_at' => now()]);
        unset($this->items);
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
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-project-page-header
        :title="__('Notifications')"
        :description="__('Notifications addressed to you in this workspace, newest first.')">
        <x-slot:actions>
            <flux:button wire:click="markAllRead" size="sm" variant="subtle">{{ __('Mark all read') }}</flux:button>
        </x-slot:actions>
    </x-project-page-header>

    <x-data-table
        :count="$this->items->count()"
        :count-label="__('recent')"
        :empty="$this->items->isEmpty()"
        :empty-message="__('No notifications yet.')">
        <flux:table class="[&_td]:align-top">
            <flux:table.columns>
                <flux:table.column>{{ __('When') }}</flux:table.column>
                <flux:table.column>{{ __('Notification') }}</flux:table.column>
                <flux:table.column>{{ __('From') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->items as $item)
                    <flux:table.row :class="$item->read_at === null ? 'font-medium' : 'text-zinc-500 dark:text-zinc-400'">
                        <flux:table.cell class="whitespace-nowrap">
                            <span title="{{ $item->created_at?->toIso8601String() }}">{{ $item->created_at?->diffForHumans() ?? '—' }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($item->data['url'] ?? null)
                                <a href="{{ $item->data['url'] }}" wire:navigate class="hover:underline">{{ $item->data['title'] ?? __('Notification') }}</a>
                            @else
                                {{ $item->data['title'] ?? __('Notification') }}
                            @endif
                            <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $item->data['body'] ?? '' }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap text-sm">
                            {{ $item->data['sender']['name'] ?? __('System') }}
                            @if ($item->data['acting_role'] ?? null)
                                <span class="text-zinc-400 dark:text-zinc-500">· {{ $item->data['acting_role'] }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap text-right">
                            @if ($item->read_at === null)
                                <flux:button wire:click="markRead('{{ $item->id }}')" size="xs" variant="subtle">{{ __('Mark read') }}</flux:button>
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
