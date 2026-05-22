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
     * Status filter: one of the STATUS_FILTERS keys. Defaults to `open`
     * (new + triaged) so the resolved backlog does not grow the page forever.
     */
    public string $statusFilter = 'open';

    /**
     * @var array<string,list<string>> Filter key => the statuses it matches.
     *                                  An empty list means "all statuses".
     */
    private const STATUS_FILTERS = [
        'open' => ['new', 'triaged'],
        'new' => ['new'],
        'triaged' => ['triaged'],
        'resolved' => ['resolved'],
        'all' => [],
    ];

    public function updatedStatusFilter(): void
    {
        unset($this->items);
    }

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

        $statuses = self::STATUS_FILTERS[$this->statusFilter] ?? self::STATUS_FILTERS['open'];

        return ToolFeedback::query()
            ->where('workspace_id', $workspaceId)
            ->when($statuses !== [], fn ($query) => $query->whereIn('status', $statuses))
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
        :title="__('Feedback')"
        :count="$this->items->count()"
        :count-label="__('recent')"
        :empty="$this->items->isEmpty()"
        :empty-message="__('No feedback yet.')">
        <x-slot:filters>
            <flux:select wire:model.live="statusFilter" size="sm" class="max-w-3xs" data-test="feedback-status-filter">
                <flux:select.option value="open">{{ __('Open') }}</flux:select.option>
                <flux:select.option value="new">{{ __('New') }}</flux:select.option>
                <flux:select.option value="triaged">{{ __('Triaged') }}</flux:select.option>
                <flux:select.option value="resolved">{{ __('Resolved') }}</flux:select.option>
                <flux:select.option value="all">{{ __('All') }}</flux:select.option>
            </flux:select>
        </x-slot:filters>
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
                            <x-timestamp :value="$item->created_at" />
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="BadgeVariant::feedbackCategory($item->category)" size="sm">{{ EnumLabel::lower($item->category) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <a href="{{ route('feedback.show', $item) }}" wire:navigate class="font-medium hover:underline">{{ $item->summary }}</a>
                            <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400" title="{{ $item->body }}">{{ Str::limit($item->body, 160) }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ $item->tool_name ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ $item->agent?->name ?? $item->user?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="BadgeVariant::feedbackStatus($item->status)" size="sm">{{ EnumLabel::lower($item->status) }}</flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </x-data-table>
</div>
