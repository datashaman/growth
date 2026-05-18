<?php

use App\Models\Review;
use App\Support\BadgeVariant;
use Livewire\Component;

new class extends Component {
    public Review $review;

    public function mount(Review $review): void
    {
        $this->review = $review->load($this->relations());
    }

    /**
     * Relations eager-loaded for the detail view.
     *
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'project',
            'ownerRole',
            'reviewPlan',
            'participants.role',
            'findings.ownerRole',
            'targets',
        ];
    }

    /**
     * @return array<string,string>
     */
    public function getListeners(): array
    {
        return [
            'echo-private:reviews.'.$this->review->id.',ReviewDataChanged' => 'onReviewDataChanged',
        ];
    }

    public function onReviewDataChanged(): void
    {
        $this->review = $this->review->fresh($this->relations());
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-detail-page-header
        :title="$review->title"
        back-route="dashboard"
        :back-label="__('Back to dashboard')">
        <x-slot:badges>
            <flux:badge color="zinc" size="sm">{{ str_replace('_', ' ', $review->type) }}</flux:badge>
            <flux:badge :color="BadgeVariant::reviewStatus($review->status)" size="sm">{{ str_replace('_', ' ', $review->status) }}</flux:badge>
            @if ($review->decision)
                <flux:badge :color="BadgeVariant::reviewDecision($review->decision)" size="sm">{{ str_replace('_', ' ', $review->decision) }}</flux:badge>
            @endif
        </x-slot:badges>
        <x-slot:description>
            {{ __('Review in project') }} <a href="{{ route('dashboard', ['project' => $review->project_id]) }}" class="underline">{{ $review->project->name }}</a>
        </x-slot:description>

        <x-slot:actions>
            <flux:modal.trigger name="edit-review">
                <flux:button size="sm" icon="pencil-square" variant="primary">{{ __('Edit') }}</flux:button>
            </flux:modal.trigger>
            <flux:modal.trigger name="delete-review">
                <flux:button size="sm" icon="trash" variant="danger">{{ __('Delete') }}</flux:button>
            </flux:modal.trigger>
        </x-slot:actions>
    </x-detail-page-header>

    <livewire:pages::reviews.edit-modal :review-id="$review->id" :key="'edit-review-'.$review->id" />
    <livewire:pages::reviews.delete-modal :review-id="$review->id" :key="'delete-review-'.$review->id" />

    <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg" class="mb-3">{{ __('Properties') }}</flux:heading>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Owner role') }}</dt>
                <dd class="mt-0.5">{{ $review->ownerRole?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Review plan') }}</dt>
                <dd class="mt-0.5">{{ $review->reviewPlan?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Planned') }}</dt>
                <dd class="mt-0.5">{{ $review->planned_at?->format('Y-m-d') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Held') }}</dt>
                <dd class="mt-0.5">{{ $review->held_at?->format('Y-m-d') ?? '—' }}</dd>
            </div>
        </dl>
    </section>

    @if ($review->objective)
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-3">{{ __('Objective') }}</flux:heading>
            <flux:text class="whitespace-pre-line">{{ $review->objective }}</flux:text>
        </section>
    @endif

    @if ($review->summary)
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-3">{{ __('Summary') }}</flux:heading>
            <flux:text class="whitespace-pre-line">{{ $review->summary }}</flux:text>
        </section>
    @endif

    @if (! empty($review->entry_criteria) || ! empty($review->exit_criteria))
        <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            @if (! empty($review->entry_criteria))
                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="lg" class="mb-3">{{ __('Entry criteria') }}</flux:heading>
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ((array) $review->entry_criteria as $criterion)
                            <li class="text-sm">{{ $criterion }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! empty($review->exit_criteria))
                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="lg" class="mb-3">{{ __('Exit criteria') }}</flux:heading>
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ((array) $review->exit_criteria as $criterion)
                            <li class="text-sm">{{ $criterion }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    @endif

    @if ($review->participants->isNotEmpty())
        <x-data-table
            :title="__('Participants')"
            :count="$review->participants->count()">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Role') }}</flux:table.column>
                    <flux:table.column>{{ __('Responsibility') }}</flux:table.column>
                    <flux:table.column>{{ __('Attendance') }}</flux:table.column>
                    <flux:table.column>{{ __('Signed off') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($review->participants as $participant)
                        <flux:table.row>
                            <flux:table.cell class="font-medium">{{ $participant->role?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $participant->responsibility ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $participant->attendance_status ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $participant->signed_off_at?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif

    @if ($review->findings->isNotEmpty())
        <x-data-table
            :title="__('Findings')"
            :count="$review->findings->count()">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Finding') }}</flux:table.column>
                    <flux:table.column>{{ __('Severity') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Owner') }}</flux:table.column>
                    <flux:table.column>{{ __('Due') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($review->findings as $finding)
                        <flux:table.row>
                            <flux:table.cell>
                                <div class="font-medium">{{ $finding->title }}</div>
                                @if ($finding->description)
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ \Illuminate\Support\Str::limit($finding->description, 80) }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::finding($finding->severity)" size="sm">{{ $finding->severity }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ str_replace('_', ' ', $finding->status) }}</flux:table.cell>
                            <flux:table.cell>{{ $finding->ownerRole?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $finding->due_at?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif

    @if ($review->targets->isNotEmpty())
        <x-data-table
            :title="__('Targets')"
            :count="$review->targets->count()">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                    <flux:table.column>{{ __('Reference') }}</flux:table.column>
                    <flux:table.column>{{ __('Context') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($review->targets as $target)
                        <flux:table.row>
                            <flux:table.cell>{{ class_basename($target->reviewable_type) }}</flux:table.cell>
                            <flux:table.cell class="font-mono text-xs">{{ $target->reviewable_id }}</flux:table.cell>
                            <flux:table.cell>{{ $target->context ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif
</div>
