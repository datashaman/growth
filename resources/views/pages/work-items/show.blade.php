<?php

use App\Models\WorkItem;
use App\Support\BadgeVariant;
use App\Support\EnumLabel;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component {
    public WorkItem $workItem;

    public function mount(WorkItem $workItem): void
    {
        $this->workItem = $workItem->load($this->relations());
    }

    public function rendering(View $view): void
    {
        $view->title($this->workItem->reference().' — '.$this->workItem->name);
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
            'parent',
            'children',
            'responsibleRole',
            'requirements',
            'milestones',
            'dependencies',
            'deliveryLinks.checkRuns',
            'releases',
            'mockups',
        ];
    }

    /**
     * @return array<string,string>
     */
    public function getListeners(): array
    {
        return [
            'echo-private:projects.'.$this->workItem->project_id.',ProjectDataChanged' => 'onProjectDataChanged',
        ];
    }

    public function onProjectDataChanged(): void
    {
        $this->workItem = $this->workItem->fresh($this->relations());
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-detail-page-header
        :title="$workItem->reference().' — '.$workItem->name"
        back-route="plan"
        :back-label="__('Back to plan')">
        <x-slot:badges>
            <flux:badge :color="BadgeVariant::workItemKind($workItem->kind)" size="sm">{{ str_replace('_', ' ', $workItem->kind) }}</flux:badge>
            <flux:badge :color="BadgeVariant::workItemStatus($workItem->status)" size="sm">{{ str_replace('_', ' ', $workItem->status) }}</flux:badge>
        </x-slot:badges>
        <x-slot:description>
            {{ __('Work item in project') }} <a href="{{ route('dashboard', ['project' => $workItem->project_id]) }}" class="underline">{{ $workItem->project->name }}</a>
        </x-slot:description>

    </x-detail-page-header>

    <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg" class="mb-3">{{ __('Properties') }}</flux:heading>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Responsible role') }}</dt>
                <dd class="mt-0.5">{{ $workItem->responsibleRole?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Parent') }}</dt>
                <dd class="mt-0.5">
                    @if ($workItem->parent)
                        <a href="{{ route('work-items.show', $workItem->parent) }}" wire:navigate class="underline">{{ $workItem->parent->name }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Needs mockups') }}</dt>
                <dd class="mt-0.5">{{ $workItem->needs_mockups ? __('Yes') : __('No') }}</dd>
            </div>
        </dl>
    </section>

    @if ($workItem->description)
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-3">{{ __('Description') }}</flux:heading>
            <flux:text class="whitespace-pre-line">{{ $workItem->description }}</flux:text>
        </section>
    @endif

    @if ($workItem->children->isNotEmpty())
        <x-data-table
            :title="__('Children')"
            :count="$workItem->children->count()">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Kind') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($workItem->children as $child)
                        <flux:table.row>
                            <flux:table.cell>
                                <a href="{{ route('work-items.show', $child) }}" wire:navigate class="font-medium hover:underline">{{ $child->name }}</a>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::workItemKind($child->kind)" size="sm">{{ str_replace('_', ' ', $child->kind) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::workItemStatus($child->status)" size="sm">{{ str_replace('_', ' ', $child->status) }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif

    @if ($workItem->requirements->isNotEmpty())
        <x-data-table
            :title="__('Linked requirements')"
            :count="$workItem->requirements->count()">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Requirement') }}</flux:table.column>
                    <flux:table.column>{{ __('Doc') }}</flux:table.column>
                    <flux:table.column>{{ __('Priority') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($workItem->requirements as $req)
                        <flux:table.row>
                            <flux:table.cell>
                                <a href="{{ route('requirements.show', $req) }}" wire:navigate class="hover:underline">{{ \Illuminate\Support\Str::limit($req->text, 80) }}</a>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::doc($req->doc)" size="sm">{{ strtoupper($req->doc) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::priority($req->priority)" size="sm">{{ $req->priority ?? '—' }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif

    @if ($workItem->milestones->isNotEmpty())
        <x-data-table
            :title="__('Milestones')"
            :count="$workItem->milestones->count()">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Milestone') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($workItem->milestones as $milestone)
                        <flux:table.row>
                            <flux:table.cell class="font-medium">{{ $milestone->name }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::milestoneStatus($milestone->status)" size="sm">{{ EnumLabel::lower($milestone->status) }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif

    @if ($workItem->dependencies->isNotEmpty())
        <x-data-table
            :title="__('Dependencies')"
            :count="$workItem->dependencies->count()"
            :count-label="__('upstream')">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Depends on') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($workItem->dependencies as $dep)
                        <flux:table.row>
                            <flux:table.cell>
                                <a href="{{ route('work-items.show', $dep) }}" wire:navigate class="font-medium hover:underline">{{ $dep->name }}</a>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::workItemStatus($dep->status)" size="sm">{{ str_replace('_', ' ', $dep->status) }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif

    @if ($workItem->deliveryLinks->isNotEmpty())
        <x-data-table
            :title="__('Delivery links')"
            :count="$workItem->deliveryLinks->count()">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                    <flux:table.column>{{ __('Ref') }}</flux:table.column>
                    <flux:table.column>{{ __('Checks') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($workItem->deliveryLinks as $link)
                        <flux:table.row>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::deliveryType($link->type)" size="sm">{{ str_replace('_', ' ', $link->type) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($link->url)
                                    <a href="{{ $link->url }}" target="_blank" rel="noopener" class="font-mono text-xs text-sky-600 underline dark:text-sky-400">{{ $link->ref }}</a>
                                @else
                                    <span class="font-mono text-xs">{{ $link->ref }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($link->checkRuns->isEmpty())
                                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No checks') }}</flux:text>
                                @else
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($link->checkRuns as $check)
                                            <flux:badge :color="BadgeVariant::checkConclusion($check->conclusion)" size="sm">
                                                {{ $check->name }}{{ $check->conclusion ? ': '.$check->conclusion : '' }}
                                            </flux:badge>
                                        @endforeach
                                    </div>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif

    @if ($workItem->releases->isNotEmpty())
        <x-data-table
            :title="__('Releases')"
            :count="$workItem->releases->count()">
            <flux:table class="[&_td]:align-top">
                <flux:table.columns>
                    <flux:table.column>{{ __('Version') }}</flux:table.column>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Released') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($workItem->releases as $release)
                        <flux:table.row>
                            <flux:table.cell class="font-medium tabular-nums">{{ $release->version }}</flux:table.cell>
                            <flux:table.cell>{{ $release->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="BadgeVariant::releaseStatus($release->status)" size="sm">{{ EnumLabel::lower($release->status) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $release->released_at?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-data-table>
    @endif

    @if ($workItem->mockups->isNotEmpty())
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-3">{{ __('Spec mockups') }}</flux:heading>
            <ul class="flex flex-col gap-1">
                @foreach ($workItem->mockups as $mockup)
                    <li>
                        <flux:link :href="route('mockups.show', $mockup)" wire:navigate class="underline">
                            {{ $mockup->name === \App\Models\SpecMockup::DEFAULT_NAME ? __('Default') : $mockup->name }}
                        </flux:link>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
