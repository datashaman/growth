<?php

use App\Models\Project;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $projectId = null;

    public string $name = '';
    public string $confirmation = '';

    /** @var array<string,int> */
    public array $counts = [];

    #[On('delete-project')]
    public function load(string $projectId): void
    {
        $project = Project::query()
            ->withCount([
                'stakeholders',
                'concerns',
                'requirements',
                'designViews',
                'testPlans',
                'workItems',
                'changeRequests',
                'reviews',
                'releases',
                'deployments',
                'risks',
                'anomalies',
                'roles',
                'milestones',
            ])
            ->find($projectId);

        abort_if($project === null, 404);

        $this->projectId = $projectId;
        $this->name = $project->name;
        $this->confirmation = '';
        $this->counts = collect([
            'stakeholders' => $project->stakeholders_count,
            'concerns' => $project->concerns_count,
            'requirements' => $project->requirements_count,
            'designViews' => $project->design_views_count,
            'testPlans' => $project->test_plans_count,
            'workItems' => $project->work_items_count,
            'changeRequests' => $project->change_requests_count,
            'reviews' => $project->reviews_count,
            'releases' => $project->releases_count,
            'deployments' => $project->deployments_count,
            'risks' => $project->risks_count,
            'anomalies' => $project->anomalies_count,
            'roles' => $project->roles_count,
            'milestones' => $project->milestones_count,
        ])->filter()->all();

        $this->modal('delete-project')->show();
    }

    public function delete(): void
    {
        $project = Project::find($this->projectId);

        abort_if($project === null, 404);

        $this->validate([
            'confirmation' => ['required', 'in:'.$project->name],
        ], [
            'confirmation.in' => __('Type the project name exactly to confirm deletion.'),
        ]);

        $project->delete();

        if (session('selected_project_id') === $this->projectId) {
            session()->forget('selected_project_id');
        }

        $this->modal('delete-project')->close();
        $this->redirect(route('dashboard'), navigate: true);
    }
}; ?>

<flux:modal name="delete-project" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete this project?') }}</flux:heading>
            @if ($name)
                <flux:subheading>{{ __('“:name” and all its data will be permanently removed.', ['name' => $name]) }}</flux:subheading>
            @endif
            @if (! empty($counts))
                <flux:callout icon="exclamation-triangle" color="red" class="mt-3">
                    <flux:callout.heading>{{ __('This will delete:') }}</flux:callout.heading>
                    <flux:callout.text>
                        <ul class="list-disc pl-5">
                            @foreach ($counts as $label => $value)
                                <li>{{ $value }} {{ __(\Illuminate\Support\Str::headline($label)) }}</li>
                            @endforeach
                        </ul>
                    </flux:callout.text>
                </flux:callout>
            @endif
        </div>

        <flux:input wire:model="confirmation" :label="__('Type the project name to confirm')" :placeholder="$name" required />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="danger">{{ __('Delete project') }}</flux:button>
        </div>
    </form>
</flux:modal>
