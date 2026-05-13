<?php

use App\Models\Risk;
use App\Support\BadgeVariant;
use Livewire\Attributes\Title;
use Livewire\Component;

new class extends Component {
    public Risk $risk;

    public function mount(Risk $risk): void
    {
        $this->risk = $risk->load(['ownerRole', 'project']);
    }

    #[Title('Risk')]
    public function rendering(): void {}
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-detail-page-header
        :title="$risk->title"
        back-route="dashboard"
        :back-label="__('Back to dashboard')">
        <x-slot:badges>
            <flux:badge :color="BadgeVariant::riskExposure($risk->probability, $risk->impact)" size="sm">
                {{ BadgeVariant::riskExposureLabel($risk->probability, $risk->impact) }}
            </flux:badge>
            <flux:badge :color="BadgeVariant::riskStatus($risk->status)" size="sm">
                {{ str_replace('_', ' ', $risk->status) }}
            </flux:badge>
            <flux:badge color="zinc" size="sm">{{ str_replace('_', ' ', $risk->category) }}</flux:badge>
        </x-slot:badges>

        <x-slot:description>
            {{ __('Risk in project') }} <a href="{{ route('dashboard', ['project' => $risk->project_id]) }}" class="underline">{{ $risk->project->name }}</a>
        </x-slot:description>
    </x-detail-page-header>

    <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg" class="mb-3">{{ __('Properties') }}</flux:heading>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Probability') }}</dt>
                <dd class="mt-0.5">{{ $risk->probability ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Impact') }}</dt>
                <dd class="mt-0.5">{{ $risk->impact ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Owner role') }}</dt>
                <dd class="mt-0.5">{{ $risk->ownerRole?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Created') }}</dt>
                <dd class="mt-0.5">{{ $risk->created_at?->format('Y-m-d') ?? '—' }}</dd>
            </div>
        </dl>
    </section>

    @if ($risk->description)
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-3">{{ __('Description') }}</flux:heading>
            <flux:text class="whitespace-pre-line">{{ $risk->description }}</flux:text>
        </section>
    @endif

    @if ($risk->mitigation_plan)
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-3">{{ __('Mitigation plan') }}</flux:heading>
            <flux:text class="whitespace-pre-line">{{ $risk->mitigation_plan }}</flux:text>
        </section>
    @endif
</div>
