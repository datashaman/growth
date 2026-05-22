<?php

use App\Models\ToolInvocation;
use App\Support\TableColumn;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tool invocations')] class extends Component {
    /**
     * Result filter: 'all', 'ok' (succeeded), or 'error' (failed).
     */
    public string $resultFilter = 'all';

    /**
     * Transport filter; 'all' shows every transport.
     */
    public string $transportFilter = 'all';

    /**
     * Tool-name filter; 'all' shows every tool.
     */
    public string $toolFilter = 'all';

    /**
     * @return array<string,string>
     */
    public function getListeners(): array
    {
        $workspaceId = $this->workspaceId();

        if ($workspaceId === null) {
            return [];
        }

        return [
            'echo-private:workspaces.'.$workspaceId.',WorkspaceDataChanged' => 'onWorkspaceDataChanged',
        ];
    }

    public function onWorkspaceDataChanged(): void
    {
        unset($this->invocations, $this->transports, $this->tools);
    }

    public function updatedResultFilter(): void
    {
        unset($this->invocations);
    }

    public function updatedTransportFilter(): void
    {
        unset($this->invocations);
    }

    public function updatedToolFilter(): void
    {
        unset($this->invocations);
    }

    private function workspaceId(): ?string
    {
        return auth()->user()?->active_workspace_id;
    }

    #[Computed]
    public function invocations()
    {
        $workspaceId = $this->workspaceId();

        if ($workspaceId === null) {
            return collect();
        }

        return ToolInvocation::query()
            ->where('workspace_id', $workspaceId)
            ->when($this->resultFilter === 'ok', fn ($query) => $query->where('success', true))
            ->when($this->resultFilter === 'error', fn ($query) => $query->where('success', false))
            ->when($this->transportFilter !== 'all', fn ($query) => $query->where('transport', $this->transportFilter))
            ->when($this->toolFilter !== 'all', fn ($query) => $query->where('tool_name', $this->toolFilter))
            ->with(['user', 'agent'])
            ->orderByDesc('started_at')
            ->limit(100)
            ->get();
    }

    /**
     * Distinct transports present in this workspace, for the transport filter.
     *
     * @return list<string>
     */
    #[Computed]
    public function transports(): array
    {
        $workspaceId = $this->workspaceId();

        if ($workspaceId === null) {
            return [];
        }

        return ToolInvocation::query()
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('transport')
            ->distinct()
            ->orderBy('transport')
            ->pluck('transport')
            ->all();
    }

    /**
     * Distinct tool names present in this workspace, for the tool filter.
     *
     * @return list<string>
     */
    #[Computed]
    public function tools(): array
    {
        $workspaceId = $this->workspaceId();

        if ($workspaceId === null) {
            return [];
        }

        return ToolInvocation::query()
            ->where('workspace_id', $workspaceId)
            ->distinct()
            ->orderBy('tool_name')
            ->pluck('tool_name')
            ->all();
    }

    public function isFiltered(): bool
    {
        return $this->resultFilter !== 'all'
            || $this->transportFilter !== 'all'
            || $this->toolFilter !== 'all';
    }

    public function clearFilters(): void
    {
        $this->resultFilter = 'all';
        $this->transportFilter = 'all';
        $this->toolFilter = 'all';

        unset($this->invocations);
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
        :empty-message="$this->isFiltered() ? __('No tool invocations match the current filter.') : __('No tool invocations yet.')">
        <x-slot:header>
            <div class="flex w-full flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-baseline gap-3">
                    <flux:heading size="lg">{{ __('Tool invocations') }}</flux:heading>
                    <flux:text class="whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $this->invocations->count() }} {{ __('recent') }}
                    </flux:text>
                </div>
                <div class="grid w-full grid-cols-1 gap-2 sm:w-auto sm:grid-cols-3 lg:justify-end">
                    <flux:select wire:model.live="resultFilter" size="sm" class="w-full sm:w-48" data-test="tool-invocations-result-filter">
                        <flux:select.option value="all">{{ __('All results') }}</flux:select.option>
                        <flux:select.option value="ok">{{ __('Succeeded') }}</flux:select.option>
                        <flux:select.option value="error">{{ __('Errors only') }}</flux:select.option>
                    </flux:select>
                    <flux:select wire:model.live="transportFilter" size="sm" class="w-full sm:w-48" data-test="tool-invocations-transport-filter">
                        <flux:select.option value="all">{{ __('All transports') }}</flux:select.option>
                        @foreach ($this->transports as $transport)
                            <flux:select.option value="{{ $transport }}">{{ $transport }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="toolFilter" size="sm" class="w-full sm:w-48" data-test="tool-invocations-tool-filter">
                        <flux:select.option value="all">{{ __('All tools') }}</flux:select.option>
                        @foreach ($this->tools as $tool)
                            <flux:select.option value="{{ $tool }}">{{ $tool }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @if ($this->isFiltered())
                        <flux:button wire:click="clearFilters" size="sm" variant="subtle" class="sm:col-span-3 lg:col-span-1" data-test="tool-invocations-clear-filters">
                            {{ __('Clear filters') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        </x-slot:header>
        @php($showSurface = TableColumn::hasValues($this->invocations, fn ($invocation) => $invocation->acting_surface))
        @php($showRole = TableColumn::hasValues($this->invocations, fn ($invocation) => $invocation->acting_role_name))
        <flux:table class="[&_td]:align-top">
            <flux:table.columns>
                <flux:table.column>{{ __('Time') }}</flux:table.column>
                <flux:table.column>{{ __('Tool') }}</flux:table.column>
                <flux:table.column>{{ __('Caller') }}</flux:table.column>
                @if ($showSurface)
                    <flux:table.column>{{ __('Surface') }}</flux:table.column>
                @endif
                @if ($showRole)
                    <flux:table.column>{{ __('Role') }}</flux:table.column>
                @endif
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
                        @if ($showSurface)
                            <flux:table.cell>
                                @if ($invocation->acting_surface)
                                    <flux:badge color="zinc" size="sm">{{ $invocation->acting_surface }}</flux:badge>
                                @else
                                    <span class="text-zinc-400 dark:text-zinc-500">{{ __('unbound') }}</span>
                                @endif
                            </flux:table.cell>
                        @endif
                        @if ($showRole)
                            <flux:table.cell>
                                @if ($invocation->acting_role_name)
                                    {{ $invocation->acting_role_name }}
                                @else
                                    <span class="text-zinc-400 dark:text-zinc-500">—</span>
                                @endif
                            </flux:table.cell>
                        @endif
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
