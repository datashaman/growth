<?php

use App\Models\SpecMockup;
use Livewire\Component;

new class extends Component {
    public SpecMockup $mockup;

    public function mount(SpecMockup $mockup): void
    {
        $this->mockup = $mockup->load('workItem.project');
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
        <iframe
            src="{{ route('mockups.raw', $mockup) }}"
            sandbox="allow-scripts"
            title="{{ $mockup->name }}"
            class="h-[70vh] w-full rounded-lg border border-zinc-200 bg-white dark:border-zinc-700"></iframe>
    </section>
</div>
