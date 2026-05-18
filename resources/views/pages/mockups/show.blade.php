<?php

use App\Models\SpecMockup;
use Livewire\Component;

new class extends Component {
    public SpecMockup $mockup;

    /** ULID of the revision currently shown in the iframe. */
    public string $revisionId = '';

    public function mount(SpecMockup $mockup): void
    {
        $this->mockup = $mockup->load('workItem.project', 'workItem.mockups', 'revisions');
        $this->revisionId = (string) ($this->mockup->revisions->last()?->id ?? '');
    }

    /**
     * Switch the iframe to a past revision — only if it belongs to this mockup.
     */
    public function selectRevision(string $revisionId): void
    {
        if ($this->mockup->revisions->contains('id', $revisionId)) {
            $this->revisionId = $revisionId;
        }
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-detail-page-header
        :title="$mockup->name"
        :back-href="route('work-items.show', $mockup->workItem)"
        :back-label="__('Back to work item')">
        <x-slot:description>
            {{ __('Spec mockup for') }}
            <a href="{{ route('work-items.show', $mockup->workItem) }}" wire:navigate class="underline">{{ $mockup->workItem->reference().' — '.$mockup->workItem->name }}</a>
        </x-slot:description>
    </x-detail-page-header>

    <section class="flex flex-1 flex-col rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg" class="mb-1">{{ __('Mockup') }}</flux:heading>
        <flux:text class="mb-3 text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Agent-authored HTML, rendered in an isolated sandbox.') }}
        </flux:text>

        @if ($mockup->workItem->mockups->count() > 1)
            <nav class="mb-3 flex flex-wrap gap-2" aria-label="{{ __('Mockups') }}">
                @foreach ($mockup->workItem->mockups as $alternative)
                    @if ($alternative->is($mockup))
                        <span class="rounded-md bg-sky-600 px-2.5 py-1 text-sm font-medium text-white" aria-current="true">{{ $alternative->name }}</span>
                    @else
                        <a href="{{ route('mockups.show', $alternative) }}" wire:navigate
                            class="rounded-md bg-zinc-100 px-2.5 py-1 text-sm text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">{{ $alternative->name }}</a>
                    @endif
                @endforeach
            </nav>
        @endif

        @if ($mockup->revisions->count() > 1)
            <nav class="mb-3 flex flex-wrap gap-2" aria-label="{{ __('Revisions') }}">
                @foreach ($mockup->revisions->sortByDesc('number') as $revision)
                    <button type="button" wire:click="selectRevision('{{ $revision->id }}')"
                        @class([
                            'rounded-md px-2.5 py-1 text-sm',
                            'bg-sky-600 font-medium text-white' => $revision->id === $revisionId,
                            'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' => $revision->id !== $revisionId,
                        ])
                        @if ($revision->id === $revisionId) aria-current="true" @endif>
                        {{ __('Revision :number', ['number' => $revision->number]) }}@if ($revision->is($mockup->revisions->last())) · {{ __('current') }}@endif
                    </button>
                @endforeach
            </nav>
        @endif

        <iframe
            wire:key="revision-{{ $revisionId }}"
            src="{{ route('mockups.raw', ['mockup' => $mockup, 'revision' => $revisionId]) }}"
            sandbox="allow-scripts"
            title="{{ $mockup->name }}"
            class="h-[70vh] w-full rounded-lg border border-zinc-200 bg-white dark:border-zinc-700"></iframe>
    </section>
</div>
