<?php

use App\Models\User;
use App\Support\SurfaceContext;
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
        /** @var User $user */
        $user = auth()->user();

        // A surface-bound session has no lens choice: the lens is projected
        // from the capability surface. Ignore any attempt to change it.
        if (app(SurfaceContext::class)->surface() !== null) {
            $this->selectedLens = $user->lens()->value;

            return;
        }

        $lens = ViewLens::tryFrom($value);

        if ($lens === null) {
            $this->selectedLens = $user->lens()->value;

            return;
        }

        if ($lens === $user->lens()) {
            return;
        }

        $user->switchLens($lens);

        $this->redirect('/'.ltrim(Livewire::originalPath(), '/'), navigate: true);
    }
}; ?>

@php($surface = app(SurfaceContext::class)->surface())

<div class="relative w-full">
    @if ($surface !== null)
        <flux:icon.funnel class="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-zinc-500 dark:text-zinc-400" />
        <div
            class="flex h-8 w-full items-center ps-8 pe-2 text-sm text-zinc-500 dark:text-zinc-400"
            title="{{ __('Lens is fixed by the :surface surface', ['surface' => $surface->label()]) }}">
            {{ __($surface->lens()->label()) }}
        </div>
    @else
        <flux:icon.funnel class="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-zinc-500 dark:text-zinc-400" />
        <flux:select wire:model.live="selectedLens" size="sm" class="w-full ps-8" :placeholder="__('Select a lens')">
            @foreach (ViewLens::cases() as $lens)
                <flux:select.option value="{{ $lens->value }}">{{ __($lens->label()) }}</flux:select.option>
            @endforeach
        </flux:select>
    @endif
</div>
