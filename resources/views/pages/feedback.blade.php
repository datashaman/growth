<?php

use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\ReopenFeedback;
use App\Growth\Transitions\ResolveFeedback;
use App\Growth\Transitions\Transition;
use App\Growth\Transitions\TriageFeedback;
use App\Models\ToolFeedback;
use App\Support\BadgeVariant;
use App\Support\EnumLabel;
use Flux\Flux;
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

    public function triage(string $id): void
    {
        $this->applyTransition(new TriageFeedback, $id);
    }

    public function resolve(string $id): void
    {
        $this->applyTransition(new ResolveFeedback, $id);
    }

    public function reopen(string $id): void
    {
        $this->applyTransition(new ReopenFeedback, $id);
    }

    private function applyTransition(Transition $transition, string $id): void
    {
        $feedback = ToolFeedback::query()
            ->where('workspace_id', auth()->user()?->active_workspace_id)
            ->find($id);

        abort_if($feedback === null, 404);

        try {
            $transition->apply($feedback, auth()->user());
        } catch (IllegalTransitionException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        unset($this->items);

        Flux::toast(variant: 'success', text: __('Feedback is now :status.', [
            'status' => str_replace('_', ' ', $feedback->status),
        ]));
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
        :description="__('Feedback agents submitted about the MCP tools, newest first. Triage and resolve as you work through it.')" />

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
                <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
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
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                @if ($item->status === 'new')
                                    <flux:button size="sm" icon="inbox-arrow-down" wire:click="triage('{{ $item->id }}')">{{ __('Triage') }}</flux:button>
                                @endif
                                @if (in_array($item->status, ['new', 'triaged'], true))
                                    <flux:button size="sm" icon="check" variant="primary" wire:click="resolve('{{ $item->id }}')">{{ __('Resolve') }}</flux:button>
                                @endif
                                @if (in_array($item->status, ['triaged', 'resolved'], true))
                                    <flux:button size="sm" icon="arrow-path" wire:click="reopen('{{ $item->id }}')">{{ __('Reopen') }}</flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </x-data-table>
</div>
