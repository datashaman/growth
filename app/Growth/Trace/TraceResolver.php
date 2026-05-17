<?php

namespace App\Growth\Trace;

use App\Models\Agent;
use App\Models\Anomaly;
use App\Models\ArtifactRelation;
use App\Models\ChangeApprovalEvent;
use App\Models\ChangeImpact;
use App\Models\ChangeRequest;
use App\Models\CheckRunEvidence;
use App\Models\Citation;
use App\Models\Concern;
use App\Models\CustomViewpoint;
use App\Models\Deployment;
use App\Models\DesignElement;
use App\Models\DesignView;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectPlan;
use App\Models\Release;
use App\Models\Requirement;
use App\Models\Review;
use App\Models\ReviewFinding;
use App\Models\ReviewParticipant;
use App\Models\ReviewTarget;
use App\Models\Risk;
use App\Models\Role;
use App\Models\Source;
use App\Models\Stakeholder;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\TestRun;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemDeliveryLink;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves an artifact ID to its type, then walks the trace graph
 * outward to a configurable depth. Returns flat nodes + edges suitable
 * for an MCP structured response.
 */
class TraceResolver
{
    /**
     * Type → model class. Order matters only for ID lookup performance.
     */
    private const TYPES = [
        'requirement' => Requirement::class,
        'artifact_relation' => ArtifactRelation::class,
        'change_request' => ChangeRequest::class,
        'change_approval_event' => ChangeApprovalEvent::class,
        'change_impact' => ChangeImpact::class,
        'check_run_evidence' => CheckRunEvidence::class,
        'concern' => Concern::class,
        'deployment' => Deployment::class,
        'design_view' => DesignView::class,
        'design_element' => DesignElement::class,
        'custom_viewpoint' => CustomViewpoint::class,
        'test_case' => TestCase::class,
        'test_plan' => TestPlan::class,
        'test_run' => TestRun::class,
        'anomaly' => Anomaly::class,
        'stakeholder' => Stakeholder::class,
        'project' => Project::class,
        'source' => Source::class,
        'citation' => Citation::class,
        'project_plan' => ProjectPlan::class,
        'release' => Release::class,
        'milestone' => Milestone::class,
        'work_item' => WorkItem::class,
        'work_item_delivery_link' => WorkItemDeliveryLink::class,
        'risk' => Risk::class,
        'review' => Review::class,
        'review_target' => ReviewTarget::class,
        'review_participant' => ReviewParticipant::class,
        'review_finding' => ReviewFinding::class,
        'role' => Role::class,
        'agent' => Agent::class,
        'user' => User::class,
    ];

    /**
     * Edges per type. Each entry: [relationship_method, target_type, edge_label, direction]
     * Direction: 'up' (toward source/parents) or 'down' (toward dependents).
     */
    private const EDGES = [
        'requirement' => [
            ['parent',       'requirement', 'derives_from', 'up'],
            ['children',     'requirement', 'derived',      'down'],
            ['testCases',    'test_case',   'verified_by',  'down'],
            ['anomalies',    'anomaly',     'affected_by',  'down'],
            ['citations',    'citation',    'cited_by',     'up'],
            ['workItems',    'work_item',   'covered_by',   'down'],
            ['reviewTargets', 'review_target', 'reviewed_in', 'up'],
            ['changeImpacts', 'change_impact', 'impacted_by', 'up'],
        ],
        'artifact_relation' => [
            ['sourceArtifact', null, 'source', 'up'],
            ['targetArtifact', null, 'target', 'down'],
        ],
        'concern' => [
            ['raisedBy',     'stakeholder', 'raised_by',    'up'],
            ['designViews',  'design_view', 'framed_by',    'down'],
            ['citations',    'citation',    'cited_by',     'up'],
        ],
        'stakeholder' => [
            ['concerns',     'concern',     'raised',       'down'],
        ],
        'design_view' => [
            ['concerns',     'concern',     'addresses',    'up'],
            ['elements',     'design_element', 'contains',  'down'],
            ['citations',    'citation',    'cited_by',     'up'],
        ],
        'design_element' => [
            ['view',         'design_view', 'in_view',      'up'],
        ],
        'custom_viewpoint' => [
            ['citations',    'citation',    'cited_by',     'up'],
        ],
        'test_plan' => [
            ['cases',        'test_case',   'contains_case', 'down'],
        ],
        'test_case' => [
            ['plan',         'test_plan',   'in_plan',      'up'],
            ['requirements', 'requirement', 'verifies',     'up'],
            ['runs',         'test_run',    'executed_as',  'down'],
            ['citations',    'citation',    'cited_by',     'up'],
        ],
        'test_run' => [
            ['case',         'test_case',   'runs_case',    'up'],
            ['anomalies',    'anomaly',     'surfaced',     'down'],
        ],
        'anomaly' => [
            ['testRun',      'test_run',    'from_run',     'up'],
            ['affectedRequirements', 'requirement', 'affects', 'down'],
            ['citations',    'citation',    'cited_by',     'up'],
        ],
        'source' => [
            ['citations',    'citation',    'cites',        'down'],
        ],
        'citation' => [
            ['source',       'source',      'from_source',  'up'],
            ['citable',      null,          'cites_artifact', 'down'],
        ],
        'project_plan' => [
            ['project',      'project',     'plans',        'up'],
            ['citations',    'citation',    'cited_by',     'up'],
        ],
        'milestone' => [
            ['workItems',    'work_item',   'requires',     'down'],
            ['citations',    'citation',    'cited_by',     'up'],
        ],
        'work_item' => [
            ['parent',           'work_item',   'under',        'up'],
            ['children',         'work_item',   'includes',     'down'],
            ['requirements',     'requirement', 'covers',       'up'],
            ['milestones',       'milestone',   'delivers_to',  'up'],
            ['responsibleRole',  'role',        'owned_by',     'up'],
            ['dependencies',     'work_item',   'depends_on',   'up'],
            ['dependents',       'work_item',   'blocks',       'down'],
            ['raciRoles',        'role',        'raci',         'up'],
            ['deliveryLinks',    'work_item_delivery_link', 'implemented_by', 'down'],
            ['releases',         'release',    'released_in', 'down'],
            ['changeImpacts',    'change_impact', 'impacted_by', 'up'],
            ['citations',        'citation',    'cited_by',     'up'],
        ],
        'work_item_delivery_link' => [
            ['workItem',      'work_item',   'evidence_for', 'up'],
            ['checkRuns',     'check_run_evidence', 'validated_by', 'down'],
            ['deployments',   'deployment', 'deployed_by', 'down'],
        ],
        'check_run_evidence' => [
            ['deliveryLink',  'work_item_delivery_link', 'checks', 'up'],
        ],
        'release' => [
            ['workItems',     'work_item',   'includes', 'down'],
            ['deployments',   'deployment',  'deployed_as', 'down'],
        ],
        'deployment' => [
            ['release',       'release',     'deploys_release', 'up'],
            ['deliveryLinks', 'work_item_delivery_link', 'deploys_evidence', 'up'],
        ],
        'role' => [
            ['workItems',    'work_item',   'responsible_for', 'down'],
            ['raciWorkItems', 'work_item',   'raci_for',    'down'],
            ['risks',        'risk',        'owns_risk',   'down'],
            ['requestedChanges', 'change_request', 'requested_change', 'down'],
            ['reviews',      'review',      'owns_review', 'down'],
            ['reviewParticipants', 'review_participant', 'participates_as', 'down'],
            ['reviewFindings', 'review_finding', 'owns_finding', 'down'],
            ['users',        'user',        'filled_by',    'down'],
            ['agents',       'agent',       'filled_by',    'down'],
            ['citations',    'citation',    'cited_by',     'up'],
        ],
        'risk' => [
            ['ownerRole',    'role',        'owned_by',    'up'],
            ['citations',    'citation',    'cited_by',    'up'],
        ],
        'review' => [
            ['ownerRole',    'role',          'owned_by',     'up'],
            ['participants',  'review_participant', 'participants', 'down'],
            ['targets',      'review_target', 'targets',      'down'],
            ['findings',     'review_finding', 'records',     'down'],
            ['changeRequests', 'change_request', 'controls_change', 'down'],
            ['citations',    'citation',      'cited_by',     'up'],
        ],
        'review_target' => [
            ['review',       'review',       'in_review',     'up'],
            ['reviewable',   null,           'reviews_artifact', 'down'],
        ],
        'review_participant' => [
            ['review',       'review',       'in_review',     'up'],
            ['role',         'role',         'filled_by',     'up'],
        ],
        'review_finding' => [
            ['review',       'review',       'found_in',      'up'],
            ['ownerRole',    'role',         'owned_by',      'up'],
            ['reviewable',   null,           'against',       'up'],
            ['citations',    'citation',     'cited_by',      'up'],
        ],
        'change_request' => [
            ['requesterRole', 'role',         'requested_by',  'up'],
            ['review',        'review',       'reviewed_in',   'up'],
            ['impacts',       'change_impact', 'impacts',      'down'],
            ['approvalEvents', 'change_approval_event', 'approved_as', 'down'],
            ['citations',     'citation',     'cited_by',      'up'],
        ],
        'change_approval_event' => [
            ['changeRequest', 'change_request', 'decision_for', 'up'],
        ],
        'change_impact' => [
            ['changeRequest', 'change_request', 'from_change', 'up'],
            ['impactable',    null,            'impacts_artifact', 'down'],
        ],
        'agent' => [
            ['roles',        'role',        'fills',        'up'],
            ['citations',    'citation',    'cited_by',     'up'],
        ],
        'user' => [
            ['roles',        'role',        'fills',        'up'],
        ],
        // Project edges intentionally omitted — walking from a project would
        // include every artifact and explode the response.
    ];

    /**
     * @return array{type:string,model:Model}|null
     */
    public function resolve(string $id): ?array
    {
        foreach (self::TYPES as $type => $class) {
            if ($model = $class::find($id)) {
                return ['type' => $type, 'model' => $model];
            }
        }

        return null;
    }

    /**
     * @return array{nodes:list<array{id:string,type:string,label:string}>,edges:list<array{from:string,to:string,label:string,direction:string}>}
     */
    public function walk(Model $start, string $startType, int $maxDepth, string $directionFilter = 'both'): array
    {
        $nodes = [];
        $edges = [];
        $visited = [];
        $queue = [[$start, $startType, 0]];

        while ($queue !== []) {
            [$node, $type, $depth] = array_shift($queue);

            $key = $type.':'.$node->getKey();
            if (isset($visited[$key])) {
                continue;
            }
            $visited[$key] = true;

            $nodes[] = [
                'id' => $node->getKey(),
                'type' => $type,
                'label' => $this->labelFor($node, $type),
            ];

            if ($depth >= $maxDepth) {
                continue;
            }

            foreach (self::EDGES[$type] ?? [] as [$rel, $targetType, $label, $direction]) {
                if ($directionFilter !== 'both' && $direction !== $directionFilter) {
                    continue;
                }

                $related = $node->{$rel};
                if ($related === null) {
                    continue;
                }

                $relatedModels = is_iterable($related) ? $related : [$related];

                foreach ($relatedModels as $r) {
                    $resolvedType = $targetType ?? array_search(get_class($r), self::TYPES, true);
                    if ($resolvedType === false) {
                        continue;
                    }

                    $edges[] = [
                        'from' => $node->getKey(),
                        'to' => $r->getKey(),
                        'label' => $label,
                        'direction' => $direction,
                    ];
                    $queue[] = [$r, $resolvedType, $depth + 1];
                }
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    public function labelFor(Model $model, string $type): string
    {
        return match ($type) {
            'requirement' => mb_strimwidth($model->text, 0, 60, '…'),
            'change_request' => "[{$model->status}/{$model->priority}] {$model->title}",
            'artifact_relation' => "{$model->source_artifact_type}:{$model->source_artifact_id} {$model->relation} {$model->target_artifact_type}:{$model->target_artifact_id}",
            'change_impact' => "{$model->impact_kind}:{$model->impactable_type}:{$model->impactable_id}",
            'concern' => mb_strimwidth($model->text, 0, 60, '…'),
            'design_element' => "{$model->kind}:{$model->name}",
            'test_run' => "{$model->status} @ {$model->run_at?->toDateString()}",
            'anomaly' => "[{$model->severity}] {$model->summary}",
            'source' => "[{$model->kind}] {$model->title}",
            'citation' => $model->locator
                ? "@ {$model->locator}"
                : 'cite',
            'project_plan' => "PMP ({$model->status})",
            'work_item' => "[{$model->kind}] {$model->name}",
            'risk' => "[{$model->probability}/{$model->impact}] {$model->title}",
            'review' => "[{$model->type}/{$model->status}] {$model->title}",
            'review_target' => "{$model->reviewable_type}:{$model->reviewable_id}",
            'review_participant' => "{$model->responsibility}:{$model->role?->name}",
            'review_finding' => "[{$model->severity}/{$model->status}] {$model->title}",
            'agent' => $model->kind
                ? "{$model->name} ({$model->kind})"
                : (string) $model->name,
            'user' => (string) ($model->name ?? $model->email),
            'milestone' => (string) $model->name,
            default => (string) ($model->name ?? $model->getKey()),
        };
    }
}
