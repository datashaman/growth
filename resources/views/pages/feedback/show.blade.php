<?php

use App\Models\ToolFeedback;
use App\Support\BadgeVariant;
use App\Support\EnumLabel;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Feedback')] class extends Component {
    public ToolFeedback $feedback;

    public function mount(ToolFeedback $toolFeedback): void
    {
        abort_unless(
            $toolFeedback->workspace_id === auth()->user()?->active_workspace_id,
            404,
        );

        $this->feedback = $toolFeedback->load([
            'user', 'agent', 'project',
            'comments' => fn ($query) => $query->with('author')->orderBy('created_at'),
            'statusTransitions' => fn ($query) => $query->with('transitionedBy')->orderBy('transitioned_at'),
        ]);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-detail-page-header
        :title="$feedback->summary"
        back-route="feedback"
        :back-label="__('Back to feedback')">
        <x-slot:badges>
            <flux:badge :color="BadgeVariant::feedbackCategory($feedback->category)" size="sm">
                {{ EnumLabel::lower($feedback->category) }}
            </flux:badge>
            <flux:badge :color="BadgeVariant::feedbackStatus($feedback->status)" size="sm">
                {{ EnumLabel::lower($feedback->status) }}
            </flux:badge>
        </x-slot:badges>

        @if ($feedback->project)
            <x-slot:description>
                {{ __('Feedback in project') }}
                <a href="{{ route('dashboard', ['project' => $feedback->project_id]) }}" class="underline">{{ $feedback->project->name }}</a>
            </x-slot:description>
        @endif
    </x-detail-page-header>

    <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg" class="mb-3">{{ __('Properties') }}</flux:heading>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Tool') }}</dt>
                <dd class="mt-0.5">{{ $feedback->tool_name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Filed by') }}</dt>
                <dd class="mt-0.5">{{ $feedback->agent?->name ?? $feedback->user?->name ?? __('System') }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Created') }}</dt>
                <dd class="mt-0.5">
                    <x-timestamp :value="$feedback->created_at" />
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Last updated') }}</dt>
                <dd class="mt-0.5">
                    <x-timestamp :value="$feedback->updated_at" />
                </dd>
            </div>
        </dl>
    </section>

    <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg" class="mb-3">{{ __('Detail') }}</flux:heading>
        <flux:text class="whitespace-pre-line">{{ $feedback->body }}</flux:text>
    </section>

    <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg" class="mb-3">{{ __('Comments') }}</flux:heading>
        @if ($feedback->comments->isEmpty())
            <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('No comments yet.') }}</flux:text>
        @else
            <ol class="flex flex-col gap-4">
                @foreach ($feedback->comments as $comment)
                    <li class="flex flex-col gap-1">
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                            <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $comment->author?->name ?? __('System') }}</span>
                            @if ($comment->acting_role_name)
                                <span class="text-zinc-400 dark:text-zinc-500">· {{ $comment->acting_role_name }}</span>
                            @endif
                            @if ($comment->acting_surface)
                                <span class="text-zinc-400 dark:text-zinc-500">· {{ $comment->acting_surface }}</span>
                            @endif
                            <span class="text-zinc-400 dark:text-zinc-500">
                                · <x-timestamp :value="$comment->created_at" />
                            </span>
                        </div>
                        <flux:text class="whitespace-pre-line">{{ $comment->body }}</flux:text>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg" class="mb-3">{{ __('Status history') }}</flux:heading>
        @if ($feedback->statusTransitions->isEmpty())
            <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('No status changes yet.') }}</flux:text>
        @else
            <ol class="flex flex-col gap-3">
                @foreach ($feedback->statusTransitions as $transition)
                    <li class="flex flex-col gap-0.5 border-s-2 border-zinc-200 ps-3 dark:border-zinc-700">
                        <div class="text-sm">
                            <span class="text-zinc-500 dark:text-zinc-400">{{ str_replace('_', ' ', $transition->from_status) }}</span>
                            <span class="text-zinc-400 dark:text-zinc-500">&rarr;</span>
                            <span class="font-medium">{{ str_replace('_', ' ', $transition->to_status) }}</span>
                        </div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $transition->transitionedBy?->name ?? __('System') }}
                            @if ($transition->acting_role_name)
                                <span class="text-zinc-400 dark:text-zinc-500">· {{ $transition->acting_role_name }}</span>
                            @endif
                            @if ($transition->acting_surface)
                                <span class="text-zinc-400 dark:text-zinc-500">· {{ $transition->acting_surface }}</span>
                            @endif
                            <span class="text-zinc-400 dark:text-zinc-500">
                                · <x-timestamp :value="$transition->transitioned_at" />
                            </span>
                        </div>
                        @if ($transition->reason)
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $transition->reason }}</div>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
</div>
