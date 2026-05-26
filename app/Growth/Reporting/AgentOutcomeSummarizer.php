<?php

namespace App\Growth\Reporting;

use App\Models\Agent;
use App\Models\ProjectPlanBaseline;
use App\Models\ToolFeedback;
use App\Models\ToolInvocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregates already-recorded, agent-attributed events into a per-agent track
 * record for a workspace. Pure read-side aggregation — records nothing, mutates
 * nothing.
 *
 * Invocation-derived metrics (activity, tool usage, errors, durations) are a
 * trailing window: tool_invocations is mass-pruned after
 * {@see ToolInvocation::PRUNE_AFTER_DAYS} days. Feedback and baseline counts
 * are lifetime totals — those tables are not pruned.
 */
class AgentOutcomeSummarizer
{
    /**
     * Tool names that undo or correct prior state. Counted as neutral activity
     * the agent *performed* — not a quality or reliability signal, and never a
     * count of reverts of the agent's own work.
     *
     * @var list<string>
     */
    public const CORRECTIVE_TOOLS = [
        'reopen-anomaly',
        'reopen-finding',
        'reopen-work-item',
        'reset-work-item',
        'reopen-feedback',
        'roll-back-deployment',
    ];

    /**
     * @return array{window: array<string,mixed>, agents: list<array<string,mixed>>}
     */
    public function summarize(string $workspaceId): array
    {
        $since = Carbon::now()->subDays(ToolInvocation::PRUNE_AFTER_DAYS);

        $activity = $this->activityByAgent($workspaceId, $since);
        $toolUsage = $this->toolUsageByAgent($workspaceId, $since);
        $errors = $this->errorsByAgent($workspaceId, $since);
        $feedback = $this->feedbackByAgent($workspaceId);
        $baselines = $this->baselinesByAgent($workspaceId);

        // Iterate agents, not invocation groups: an agent with no attributed
        // work has no aggregate rows but must still appear, all-zero.
        $agents = Agent::query()->orderBy('name')->get()
            ->map(fn (Agent $agent): array => $this->trackRecord(
                $agent, $activity, $toolUsage, $errors, $feedback, $baselines,
            ))->all();

        return [
            'window' => [
                'window_days' => ToolInvocation::PRUNE_AFTER_DAYS,
                'since' => $since->toIso8601String(),
                'note' => 'Activity, tool-usage, error and duration metrics cover only invocations on or after `since` — tool_invocations rows older than window_days are pruned, so they are not lifetime totals. Feedback and baselines-authored counts are lifetime.',
            ],
            'agents' => $agents,
        ];
    }

    /**
     * @param  Collection<string,object>  $activity
     * @param  array<string,array<string,int>>  $toolUsage
     * @param  array<string,array<string,int>>  $errors
     * @param  array<string,array<string,int>>  $feedback
     * @param  Collection<string,object>  $baselines
     * @return array<string,mixed>
     */
    private function trackRecord(
        Agent $agent,
        Collection $activity,
        array $toolUsage,
        array $errors,
        array $feedback,
        Collection $baselines,
    ): array {
        $row = $activity->get($agent->id);
        $total = (int) ($row->total ?? 0);
        $successes = (int) ($row->successes ?? 0);
        $agentFeedback = $feedback[$agent->id] ?? [];

        return [
            'identity' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'kind' => $agent->kind,
                'project_id' => $agent->project_id,
            ],
            'activity' => [
                'total_invocations' => $total,
                'successes' => $successes,
                'failures' => $total - $successes,
                'success_rate' => $total > 0 ? round($successes / $total, 4) : 0.0,
                'corrective_actions' => (int) ($row->corrective ?? 0),
            ],
            'tool_usage' => $this->asObject($toolUsage[$agent->id] ?? []),
            'errors' => $this->asObject($errors[$agent->id] ?? []),
            'durations' => [
                'average_ms' => $row !== null && $row->avg_duration !== null ? (int) round((float) $row->avg_duration) : 0,
                'max_ms' => (int) ($row->max_duration ?? 0),
            ],
            'feedback' => [
                'total' => array_sum($agentFeedback),
                'by_category' => $this->asObject($agentFeedback),
            ],
            'baselines_authored' => (int) ($baselines->get($agent->id)->total ?? 0),
        ];
    }

    /**
     * Per-agent activity totals over the windowed invocations.
     *
     * @return Collection<string,object>
     */
    private function activityByAgent(string $workspaceId, Carbon $since): Collection
    {
        $placeholders = implode(',', array_fill(0, count(self::CORRECTIVE_TOOLS), '?'));

        return ToolInvocation::query()
            ->where('workspace_id', $workspaceId)
            ->where('started_at', '>=', $since)
            ->whereNotNull('agent_id')
            ->groupBy('agent_id')
            ->selectRaw('agent_id')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when success then 1 else 0 end) as successes')
            ->selectRaw('avg(duration_ms) as avg_duration')
            ->selectRaw('max(duration_ms) as max_duration')
            ->selectRaw("sum(case when tool_name in ($placeholders) then 1 else 0 end) as corrective", self::CORRECTIVE_TOOLS)
            ->get()
            ->keyBy('agent_id');
    }

    /**
     * Per-agent invocation count per tool name, over the windowed invocations.
     *
     * @return array<string,array<string,int>>
     */
    private function toolUsageByAgent(string $workspaceId, Carbon $since): array
    {
        return ToolInvocation::query()
            ->where('workspace_id', $workspaceId)
            ->where('started_at', '>=', $since)
            ->whereNotNull('agent_id')
            ->groupBy('agent_id', 'tool_name')
            ->selectRaw('agent_id, tool_name, count(*) as total')
            ->get()
            ->groupBy('agent_id')
            ->map(fn (Collection $rows): array => $rows
                ->sortBy('tool_name')
                ->mapWithKeys(fn (object $r): array => [$r->tool_name => (int) $r->total])
                ->all())
            ->all();
    }

    /**
     * Per-agent count per error_class over failed windowed invocations.
     * Failures with no error_class are omitted — they carry no class to key on.
     *
     * @return array<string,array<string,int>>
     */
    private function errorsByAgent(string $workspaceId, Carbon $since): array
    {
        return ToolInvocation::query()
            ->where('workspace_id', $workspaceId)
            ->where('started_at', '>=', $since)
            ->where('success', false)
            ->whereNotNull('agent_id')
            ->whereNotNull('error_class')
            ->groupBy('agent_id', 'error_class')
            ->selectRaw('agent_id, error_class, count(*) as total')
            ->get()
            ->groupBy('agent_id')
            ->map(fn (Collection $rows): array => $rows
                ->sortBy('error_class')
                ->mapWithKeys(fn (object $r): array => [$r->error_class => (int) $r->total])
                ->all())
            ->all();
    }

    /**
     * Per-agent lifetime feedback count per category.
     *
     * @return array<string,array<string,int>>
     */
    private function feedbackByAgent(string $workspaceId): array
    {
        return ToolFeedback::query()
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('agent_id')
            ->groupBy('agent_id', 'category')
            ->selectRaw('agent_id, category, count(*) as total')
            ->get()
            ->groupBy('agent_id')
            ->map(fn (Collection $rows): array => $rows
                ->sortBy('category')
                ->mapWithKeys(fn (object $r): array => [$r->category => (int) $r->total])
                ->all())
            ->all();
    }

    /**
     * Per-agent lifetime count of authored plan baselines. project_plan_baselines
     * has no workspace_id — isolation is enforced by joining through the plan to
     * its project's workspace.
     *
     * @return Collection<string,object>
     */
    private function baselinesByAgent(string $workspaceId): Collection
    {
        return ProjectPlanBaseline::query()
            ->join('project_plans', 'project_plans.id', '=', 'project_plan_baselines.project_plan_id')
            ->join('projects', 'projects.id', '=', 'project_plans.project_id')
            ->where('projects.workspace_id', $workspaceId)
            ->whereNotNull('baselined_by_agent_id')
            ->groupBy('baselined_by_agent_id')
            ->selectRaw('baselined_by_agent_id as agent_id, count(*) as total')
            ->get()
            ->keyBy('agent_id');
    }

    /**
     * Force an empty breakdown map to serialise as a JSON object `{}` rather
     * than an array `[]`, so the output shape is stable whether or not the
     * agent has work.
     *
     * @param  array<string,int>  $map
     * @return array<string,int>|object
     */
    private function asObject(array $map): array|object
    {
        return $map === [] ? (object) [] : $map;
    }
}
