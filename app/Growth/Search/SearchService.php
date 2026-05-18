<?php

namespace App\Growth\Search;

use App\Models\Anomaly;
use App\Models\ChangeRequest;
use App\Models\Deployment;
use App\Models\DesignElement;
use App\Models\DesignView;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Release;
use App\Models\Requirement;
use App\Models\Review;
use App\Models\Risk;
use App\Models\Role;
use App\Models\Stakeholder;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Workspace-scoped substring search across the artifact types that have a
 * detail page or a stable identity. The single query core shared by the
 * webapp Cmd-K palette and the MCP `search` tool.
 *
 * Workspace isolation is inherited, not re-implemented: `Project` carries a
 * `workspace` global scope, and every child entity is constrained through a
 * `whereHas` against its project, so the scope applies transitively.
 */
class SearchService
{
    /**
     * Shortest query the service will act on; shorter input returns nothing.
     */
    private const MIN_QUERY_LENGTH = 2;

    /**
     * Rows pulled from each entity type before in-memory ranking.
     */
    private const PER_TYPE_FETCH = 25;

    /**
     * Ranked hits retained per entity type, so one noisy type cannot crowd
     * out the palette.
     */
    private const PER_TYPE_CAP = 5;

    /**
     * Default and maximum size of the flat, ranked result set.
     */
    public const DEFAULT_LIMIT = 20;

    public const MAX_LIMIT = 50;

    /**
     * Entity types weighted up in ranking — the artifacts users jump to most.
     */
    private const BOOSTED_TYPES = ['project', 'work_item'];

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return array_map(fn (array $d): string => $d['type'], self::descriptors());
    }

    /**
     * Run the search and return a flat, ranked, per-type-capped result set.
     *
     * @param  list<string>|null  $types  Restrict to these entity types; null searches all.
     * @return Collection<int, SearchHit>
     */
    public function search(string $query, ?array $types = null, int $limit = self::DEFAULT_LIMIT): Collection
    {
        $term = mb_strtolower(trim($query));

        if (mb_strlen($term) < self::MIN_QUERY_LENGTH) {
            return collect();
        }

        $limit = max(1, min($limit, self::MAX_LIMIT));

        $hits = collect();

        foreach (self::descriptors() as $descriptor) {
            if ($types !== null && ! in_array($descriptor['type'], $types, true)) {
                continue;
            }

            $hits = $hits->merge(
                $this->searchType($descriptor, $term)
            );
        }

        return $hits
            ->sortByDesc('score')
            ->values()
            ->take($limit);
    }

    /**
     * Search a single entity type and return its capped, ranked hits.
     *
     * @param  array<string, mixed>  $descriptor
     * @return Collection<int, SearchHit>
     */
    private function searchType(array $descriptor, string $term): Collection
    {
        /** @var class-string<Model> $model */
        $model = $descriptor['model'];
        $columns = $descriptor['columns'];

        /** @var Builder $query */
        $query = $model::query();

        if ($descriptor['workspaceVia'] !== null) {
            $query->whereHas($descriptor['workspaceVia']);
        }

        if ($descriptor['projectIdVia'] !== null && $descriptor['projectIdVia'] !== 'project_id') {
            $query->with($descriptor['projectIdVia'].':id,project_id');
        }

        // Escape LIKE wildcards so a query containing `%` or `_` is matched as
        // literal text rather than as a pattern.
        $escaped = addcslashes($term, '%_\\');

        $query->where(function (Builder $sub) use ($columns, $escaped): void {
            foreach ($columns as $column) {
                $sub->orWhereRaw('lower('.$column.") like ? escape '\\'", ['%'.$escaped.'%']);
            }
        });

        // Order the fetch by the same tiers the in-memory ranking uses, so the
        // best-ranked rows always survive the PER_TYPE_FETCH cut even when a
        // type has more substring matches than that — an alphabetical cut would
        // drop a high-ranked match that happens to sort late.
        $labelColumn = $descriptor['label'];

        return $query
            ->orderByRaw(
                "case
                    when lower({$labelColumn}) like ? escape '\\' then 0
                    when lower({$labelColumn}) like ? escape '\\' then 1
                    else 2
                end",
                [$escaped.'%', '% '.$escaped.'%']
            )
            ->orderBy($labelColumn)
            ->limit(self::PER_TYPE_FETCH)
            ->get()
            ->map(fn (Model $row): SearchHit => $this->toHit($descriptor, $row, $term))
            ->sortByDesc('score')
            ->take(self::PER_TYPE_CAP)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $descriptor
     */
    private function toHit(array $descriptor, Model $row, string $term): SearchHit
    {
        [$matchedField, $tier] = $this->rank($descriptor['columns'], $row, $term);

        $label = trim((string) ($row->{$descriptor['label']} ?? ''));
        if ($label === '') {
            $label = trim((string) ($row->{$descriptor['columns'][0]} ?? ''));
        }
        if ($label === '') {
            $label = '(untitled)';
        }
        $label = mb_strimwidth($label, 0, 120, '…');

        $typeWeight = in_array($descriptor['type'], self::BOOSTED_TYPES, true) ? 1 : 0;

        return new SearchHit(
            type: $descriptor['type'],
            id: (string) $row->getKey(),
            label: $label,
            projectId: $this->projectId($descriptor, $row),
            matchedField: $matchedField,
            route: $this->route($descriptor, $row),
            score: $tier * 10 + $typeWeight,
        );
    }

    /**
     * Pick the best-matching column and its rank tier:
     * 3 = exact prefix, 2 = word-boundary match, 1 = plain substring.
     *
     * @param  list<string>  $columns
     * @return array{0: string, 1: int}
     */
    private function rank(array $columns, Model $row, string $term): array
    {
        $best = [$columns[0], 0];

        foreach ($columns as $column) {
            $value = mb_strtolower((string) ($row->{$column} ?? ''));

            if (! str_contains($value, $term)) {
                continue;
            }

            if (str_starts_with($value, $term)) {
                $tier = 3;
            } elseif (preg_match('/\b'.preg_quote($term, '/').'/u', $value) === 1) {
                $tier = 2;
            } else {
                $tier = 1;
            }

            if ($tier > $best[1]) {
                $best = [$column, $tier];
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $descriptor
     */
    private function projectId(array $descriptor, Model $row): ?string
    {
        return match ($descriptor['projectIdVia']) {
            null => (string) $row->getKey(),
            'project_id' => $row->project_id !== null ? (string) $row->project_id : null,
            default => ($parent = $row->{$descriptor['projectIdVia']}) !== null
                ? (string) $parent->project_id
                : null,
        };
    }

    /**
     * @param  array<string, mixed>  $descriptor
     */
    private function route(array $descriptor, Model $row): ?string
    {
        if ($descriptor['route'] === null) {
            return null;
        }

        if ($descriptor['routeParam']) {
            return route($descriptor['route'], $row->getKey(), false);
        }

        // Shared index pages pick their project from `?project=`; carry the
        // hit's owning project so the target page opens on the right one
        // rather than whatever was last selected in the session.
        $projectId = $this->projectId($descriptor, $row);

        return route($descriptor['route'], $projectId !== null ? ['project' => $projectId] : [], false);
    }

    /**
     * The per-entity-type search map.
     *
     * - `workspaceVia`  — relation path for the `whereHas` workspace constraint
     *                     (null for `Project`, which is scoped directly).
     * - `projectIdVia`  — how to resolve the owning project id: null = self,
     *                     `project_id` = own column, else a relation to read it from.
     * - `route` / `routeParam` — webapp navigation target; types without a
     *                     dedicated detail route land on their index page,
     *                     which is told which project to scope to via a
     *                     `?project=` query parameter.
     *
     * @return list<array<string, mixed>>
     */
    private static function descriptors(): array
    {
        return [
            ['type' => 'project', 'model' => Project::class, 'label' => 'name', 'columns' => ['name', 'description'], 'workspaceVia' => null, 'projectIdVia' => null, 'route' => 'dashboard', 'routeParam' => false],
            ['type' => 'requirement', 'model' => Requirement::class, 'label' => 'slug', 'columns' => ['slug', 'text', 'rationale'], 'workspaceVia' => 'project', 'projectIdVia' => 'project_id', 'route' => 'requirements.show', 'routeParam' => true],
            ['type' => 'work_item', 'model' => WorkItem::class, 'label' => 'name', 'columns' => ['name', 'description'], 'workspaceVia' => 'project', 'projectIdVia' => 'project_id', 'route' => 'work-items.show', 'routeParam' => true],
            ['type' => 'risk', 'model' => Risk::class, 'label' => 'title', 'columns' => ['title', 'description'], 'workspaceVia' => 'project', 'projectIdVia' => 'project_id', 'route' => 'risks.show', 'routeParam' => true],
            ['type' => 'review', 'model' => Review::class, 'label' => 'title', 'columns' => ['title', 'objective', 'summary'], 'workspaceVia' => 'project', 'projectIdVia' => 'project_id', 'route' => 'reviews.show', 'routeParam' => true],
            ['type' => 'change_request', 'model' => ChangeRequest::class, 'label' => 'title', 'columns' => ['title', 'description', 'rationale'], 'workspaceVia' => 'project', 'projectIdVia' => 'project_id', 'route' => 'change-requests.show', 'routeParam' => true],
            ['type' => 'anomaly', 'model' => Anomaly::class, 'label' => 'summary', 'columns' => ['summary', 'description'], 'workspaceVia' => 'project', 'projectIdVia' => 'project_id', 'route' => 'anomalies.show', 'routeParam' => true],
            ['type' => 'milestone', 'model' => Milestone::class, 'label' => 'name', 'columns' => ['name', 'exit_criteria'], 'workspaceVia' => 'project', 'projectIdVia' => 'project_id', 'route' => 'plan', 'routeParam' => false],
            ['type' => 'release', 'model' => Release::class, 'label' => 'name', 'columns' => ['name', 'version', 'notes'], 'workspaceVia' => 'project', 'projectIdVia' => 'project_id', 'route' => 'evidence', 'routeParam' => false],
            ['type' => 'deployment', 'model' => Deployment::class, 'label' => 'environment', 'columns' => ['environment', 'notes'], 'workspaceVia' => 'project', 'projectIdVia' => 'project_id', 'route' => 'evidence', 'routeParam' => false],
            ['type' => 'stakeholder', 'model' => Stakeholder::class, 'label' => 'name', 'columns' => ['name', 'role', 'description'], 'workspaceVia' => 'project', 'projectIdVia' => 'project_id', 'route' => 'intent', 'routeParam' => false],
            ['type' => 'design_element', 'model' => DesignElement::class, 'label' => 'name', 'columns' => ['name', 'purpose'], 'workspaceVia' => 'view.project', 'projectIdVia' => 'view', 'route' => 'architecture', 'routeParam' => false],
            ['type' => 'design_view', 'model' => DesignView::class, 'label' => 'name', 'columns' => ['name', 'description'], 'workspaceVia' => 'project', 'projectIdVia' => 'project_id', 'route' => 'architecture', 'routeParam' => false],
            ['type' => 'test_plan', 'model' => TestPlan::class, 'label' => 'name', 'columns' => ['name', 'scope', 'approach'], 'workspaceVia' => 'project', 'projectIdVia' => 'project_id', 'route' => 'verification', 'routeParam' => false],
            ['type' => 'test_case', 'model' => TestCase::class, 'label' => 'name', 'columns' => ['name', 'objective'], 'workspaceVia' => 'plan.project', 'projectIdVia' => 'plan', 'route' => 'verification', 'routeParam' => false],
            ['type' => 'role', 'model' => Role::class, 'label' => 'name', 'columns' => ['name', 'responsibilities'], 'workspaceVia' => 'project', 'projectIdVia' => 'project_id', 'route' => 'plan', 'routeParam' => false],
        ];
    }
}
