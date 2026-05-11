<?php

namespace App\Growth\Artifacts;

use App\Models\Anomaly;
use App\Models\Concern;
use App\Models\DesignElement;
use App\Models\DesignView;
use App\Models\Milestone;
use App\Models\ProjectPlan;
use App\Models\Requirement;
use App\Models\ReviewPlan;
use App\Models\Risk;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ArtifactRegistry
{
    /**
     * @var array<string, class-string<Model>>
     */
    public const TYPES = [
        'requirement' => Requirement::class,
        'concern' => Concern::class,
        'design_view' => DesignView::class,
        'design_element' => DesignElement::class,
        'test_plan' => TestPlan::class,
        'test_case' => TestCase::class,
        'anomaly' => Anomaly::class,
        'project_plan' => ProjectPlan::class,
        'milestone' => Milestone::class,
        'work_item' => WorkItem::class,
        'risk' => Risk::class,
        'review_plan' => ReviewPlan::class,
    ];

    /**
     * @return array<string, class-string<Model>>
     */
    public static function types(): array
    {
        return self::TYPES;
    }

    public static function validate(string $type, string $id, string $typeField = 'artifact_type', string $idField = 'artifact_id'): Model
    {
        if (! array_key_exists($type, self::TYPES)) {
            throw ValidationException::withMessages([
                $typeField => 'The selected artifact type is invalid.',
            ]);
        }

        $artifact = self::TYPES[$type]::find($id);
        if (! $artifact) {
            throw ValidationException::withMessages([
                $idField => 'The selected artifact is invalid.',
            ]);
        }

        return $artifact;
    }

    public static function projectId(object $artifact): ?string
    {
        return match (true) {
            isset($artifact->project_id) => $artifact->project_id,
            method_exists($artifact, 'project') => $artifact->project?->id,
            method_exists($artifact, 'plan') => $artifact->plan?->project_id,
            method_exists($artifact, 'view') => $artifact->view?->project_id,
            default => null,
        };
    }
}
