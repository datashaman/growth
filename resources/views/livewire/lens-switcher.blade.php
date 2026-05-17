<?php

use App\Models\User;
use App\Support\ViewLens;
use Livewire\Component;
use Livewire\Livewire;

new class extends Component {
    public string $selectedLens = '';

    public function mount(): void
    {
        /** @var User $user */
        $user = auth()->user();

        $this->selectedLens = $user->lens()->value;
    }

    public function updatedSelectedLens(string $value): void
    {
        $lens = ViewLens::tryFrom($value);

        if ($lens === null) {
            /** @var User $user */
            $user = auth()->user();
            $this->selectedLens = $user->lens()->value;

            return;
        }

        /** @var User $user */
        $user = auth()->user();
        $user->switchLens($lens);

        $this->redirect('/'.ltrim(Livewire::originalPath(), '/'), navigate: true);
    }
}; ?>

<div class="relative w-full">
    <flux:icon.funnel class="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-zinc-500 dark:text-zinc-400" />
    <flux:select wire:model.live="selectedLens" size="sm" class="w-full ps-8" :placeholder="__('Select a lens')">
        @foreach (ViewLens::cases() as $lens)
            <flux:select.option value="{{ $lens->value }}">{{ __($lens->label()) }}</flux:select.option>
        @endforeach
    </flux:select>
</div>
