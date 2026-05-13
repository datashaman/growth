<?php

use App\Concerns\ProjectScoped;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Intent')] class extends Component {
    use ProjectScoped;

    #[Computed]
    public function stakeholders()
    {
        return $this->selectedProject?->stakeholders()->orderBy('name')->get() ?? collect();
    }

    #[Computed]
    public function concerns()
    {
        return $this->selectedProject?->concerns()->with('raisedBy')->orderBy('created_at', 'desc')->get() ?? collect();
    }

    public function stakeholderKindVariant(string $kind): string
    {
        return match ($kind) {
            'individual' => 'blue',
            'class' => 'indigo',
            default => 'zinc',
        };
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-project-page-header
        :title="__('Intent')"
        :description="__('Stakeholders raising concerns the project must address.')"
        :options="$this->projectOptions" />

    @if ($this->selectedProject === null)
        <flux:callout icon="cursor-arrow-rays">
            <flux:callout.heading>{{ __('Select a project') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Pick a project from the dropdown to see its intent artefacts.') }}</flux:callout.text>
        </flux:callout>
    @else
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Stakeholders') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $this->stakeholders->count() }} {{ __('listed') }}</flux:text>
            </div>
            @if ($this->stakeholders->isEmpty())
                <flux:text>{{ __('No stakeholders recorded.') }}</flux:text>
            @else
                <flux:table class="[&_td]:align-top">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Role') }}</flux:table.column>
                        <flux:table.column>{{ __('Kind') }}</flux:table.column>
                        <flux:table.column>{{ __('Description') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->stakeholders as $stakeholder)
                            <flux:table.row>
                                <flux:table.cell class="font-medium">{{ $stakeholder->name }}</flux:table.cell>
                                <flux:table.cell>{{ $stakeholder->role ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$this->stakeholderKindVariant($stakeholder->kind)" size="sm">{{ $stakeholder->kind }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>{{ $stakeholder->description ?? '—' }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Concerns') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $this->concerns->count() }} {{ __('raised') }}</flux:text>
            </div>
            @if ($this->concerns->isEmpty())
                <flux:text>{{ __('No concerns raised.') }}</flux:text>
            @else
                <flux:table class="[&_td]:align-top">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Concern') }}</flux:table.column>
                        <flux:table.column>{{ __('Raised by') }}</flux:table.column>
                        <flux:table.column>{{ __('Viewpoint hints') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->concerns as $concern)
                            <flux:table.row>
                                <flux:table.cell>{{ $concern->text }}</flux:table.cell>
                                <flux:table.cell>{{ $concern->raisedBy?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($concern->viewpoint_hints)
                                        {{ is_array($concern->viewpoint_hints) ? implode(', ', $concern->viewpoint_hints) : $concern->viewpoint_hints }}
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </section>
    @endif
</div>
