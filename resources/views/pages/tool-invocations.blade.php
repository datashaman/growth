<?php

use App\Models\ToolInvocation;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tool invocations')] class extends Component {
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
        unset($this->invocations);
    }

    #[Computed]
    public function invocations()
    {
        $workspaceId = auth()->user()?->active_workspace_id;

        if ($workspaceId === null) {
            return collect();
        }

        return ToolInvocation::query()
            ->where('workspace_id', $workspaceId)
            ->with(['user', 'agent'])
            ->orderByDesc('started_at')
            ->limit(100)
            ->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-project-page-header
        :title="__('Tool invocations')"
        :description="__('Recent MCP tool calls in this workspace, newest first.')" />

    <x-data-table
        :count="$this->invocations->count()"
        :count-label="__('recent')"
        :empty="$this->invocations->isEmpty()"
        :empty-message="__('No tool invocations yet.')">
        <flux:table class="[&_td]:align-top">
            <flux:table.columns>
                <flux:table.column>{{ __('Time') }}</flux:table.column>
                <flux:table.column>{{ __('Tool') }}</flux:table.column>
                <flux:table.column>{{ __('Caller') }}</flux:table.column>
                <flux:table.column>{{ __('Transport') }}</flux:table.column>
                <flux:table.column>{{ __('Result') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Duration') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->invocations as $invocation)
                    <flux:table.row>
                        <flux:table.cell class="whitespace-nowrap">
                            <span title="{{ $invocation->started_at?->toIso8601String() }}">{{ $invocation->started_at?->diffForHumans() ?? '—' }}</span>
                        </flux:table.cell>
                        <flux:table.cell class="font-medium">{{ $invocation->tool_name }}</flux:table.cell>
                        <flux:table.cell>{{ $invocation->agent?->name ?? $invocation->user?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge color="zinc" size="sm">{{ $invocation->transport ?? '—' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($invocation->success)
                                <flux:badge color="green" size="sm">{{ __('ok') }}</flux:badge>
                            @else
                                <flux:badge color="red" size="sm">{{ __('fail') }}</flux:badge>
                                @if ($invocation->error_message)
                                    <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ \Illuminate\Support\Str::limit($invocation->error_message, 80) }}</div>
                                @endif
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $invocation->duration_ms !== null ? $invocation->duration_ms.' ms' : '—' }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </x-data-table>
</div>
