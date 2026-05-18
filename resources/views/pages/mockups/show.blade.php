<?php

use App\Models\SpecMockup;
use Livewire\Component;

new class extends Component {
    public SpecMockup $mockup;

    public function mount(SpecMockup $mockup): void
    {
        $this->mockup = $mockup->load('workItem.project', 'workItem.mockups');
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

        <iframe
            src="{{ route('mockups.raw', $mockup) }}"
            sandbox="allow-scripts"
            title="{{ $mockup->name }}"
            class="h-[70vh] w-full rounded-lg border border-zinc-200 bg-white dark:border-zinc-700"></iframe>
    </section>
</div>
