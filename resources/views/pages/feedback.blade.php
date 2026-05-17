<?php

use App\Models\ToolFeedback;
use App\Support\BadgeVariant;
use App\Support\EnumLabel;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Feedback')] class extends Component {
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

    #[Computed]
    public function items()
    {
        $workspaceId = auth()->user()?->active_workspace_id;

        if ($workspaceId === null) {
            return collect();
        }

        return ToolFeedback::query()
            ->where('workspace_id', $workspaceId)
            ->with(['user', 'agent', 'project'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-project-page-header
        :title="__('Feedback')"
        :description="__('Feedback agents submitted about the MCP tools, newest first. Triage and resolve it with the feedback MCP tools.')" />

    <x-data-table
        :count="$this->items->count()"
        :count-label="__('recent')"
        :empty="$this->items->isEmpty()"
        :empty-message="__('No feedback yet.')">
        <flux:table class="[&_td]:align-top">
            <flux:table.columns>
                <flux:table.column>{{ __('When') }}</flux:table.column>
                <flux:table.column>{{ __('Category') }}</flux:table.column>
                <flux:table.column>{{ __('Feedback') }}</flux:table.column>
                <flux:table.column>{{ __('Tool') }}</flux:table.column>
                <flux:table.column>{{ __('Caller') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->items as $item)
                    <flux:table.row>
                        <flux:table.cell class="whitespace-nowrap">
                            <span title="{{ $item->created_at?->toIso8601String() }}">{{ $item->created_at?->diffForHumans() ?? '—' }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="BadgeVariant::feedbackCategory($item->category)" size="sm">{{ EnumLabel::lower($item->category) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium">{{ $item->summary }}</div>
                            <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400" title="{{ $item->body }}">{{ Str::limit($item->body, 160) }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ $item->tool_name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $item->agent?->name ?? $item->user?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="BadgeVariant::feedbackStatus($item->status)" size="sm">{{ EnumLabel::lower($item->status) }}</flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </x-data-table>
</div>
