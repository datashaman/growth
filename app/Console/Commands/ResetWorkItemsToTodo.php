<?php

namespace App\Console\Commands;

use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\ResetWorkItem;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('work-items:reset-to-todo {project : Project ULID} {items* : Work item ULIDs to reset} {--actor= : Optional user email to record as the transition actor} {--reason=Reset to todo by operator correction. : Reason recorded on each transition}')]
#[Description('Operator correction: reset in-progress work items to todo through the audited work-item transition path.')]
class ResetWorkItemsToTodo extends Command
{
    public function handle(): int
    {
        $project = Project::find($this->argument('project'));
        if (! $project) {
            $this->error("No project found with id [{$this->argument('project')}].");

            return self::FAILURE;
        }

        $ids = array_values(array_unique($this->argument('items')));
        if ($ids === []) {
            $this->error('At least one work item ULID is required.');

            return self::FAILURE;
        }

        $actor = $this->resolveActor($project);
        if ($actor === false) {
            return self::FAILURE;
        }

        $items = WorkItem::query()
            ->where('project_id', $project->id)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $missing = array_values(array_diff($ids, $items->keys()->all()));
        if ($missing !== []) {
            $this->error('Some work items were not found in the project: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $badStatus = $items
            ->reject(fn (WorkItem $item): bool => $item->status === 'in_progress')
            ->map(fn (WorkItem $item): string => "{$item->id} ({$item->reference()} is {$item->status})")
            ->values()
            ->all();

        if ($badStatus !== []) {
            $this->error('All targeted work items must currently be in_progress: '.implode(', ', $badStatus));

            return self::FAILURE;
        }

        $reason = $this->option('reason');
        $transition = new ResetWorkItem;
        $count = 0;

        foreach ($ids as $id) {
            try {
                $transition->apply($items[$id], $actor instanceof User ? $actor : null, is_string($reason) ? $reason : null);
                $count++;
            } catch (IllegalTransitionException $exception) {
                throw new \RuntimeException("Could not reset {$id}: {$exception->getMessage()}", previous: $exception);
            }
        }

        $this->info("Reset {$count} work item(s) to todo in {$project->name}.");

        return self::SUCCESS;
    }

    private function resolveActor(Project $project): User|false|null
    {
        $email = $this->option('actor');
        if (! is_string($email) || $email === '') {
            return null;
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("No user found with email [{$email}].");

            return false;
        }

        if (! $user->workspaces()->where('workspaces.id', $project->workspace_id)->exists()) {
            $this->error("User [{$email}] does not belong to the project's workspace.");

            return false;
        }

        return $user;
    }
}
