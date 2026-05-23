<?php

namespace App\Mcp\Tools\Plan;

use App\Models\DesignView;
use App\Models\SpecMockup;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Add or refine a spec mockup on a work item or a requirement — a self-contained HTML page expressing a UI idea. Before generating the HTML, read the owner\'s mockup design brief (`growth://owners/{owner_type}/{owner_id}/mockup-design-brief`) so the artifact reflects requirements, existing mockups, and architecture context. Without `name` the owner\'s default mockup is updated in place; pass `name` to hold a named layout alternative alongside the default.')]
class UpsertMockup extends Tool
{
    use ResolvesMockupOwner;

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'owner_type' => 'required|string|in:work_item,requirement',
            'owner_id' => ['required', 'string', $this->ownerExistsRule($request->get('owner_type'))],
            'name' => 'sometimes|string|max:255',
            'html' => 'required|string',
        ]);

        $mockup = SpecMockup::firstOrCreate([
            'owner_type' => $data['owner_type'],
            'owner_id' => $data['owner_id'],
            'name' => $data['name'] ?? SpecMockup::DEFAULT_NAME,
        ]);
        $created = $mockup->wasRecentlyCreated;

        $revision = $mockup->appendRevision($data['html']);
        $owner = self::OWNER_MODELS[$data['owner_type']]::findOrFail($data['owner_id']);

        return Response::structured([
            'id' => $mockup->id,
            'owner_type' => $mockup->owner_type,
            'owner_id' => $mockup->owner_id,
            'name' => $mockup->name,
            'revision' => $revision->number,
            'created' => $created,
            'warnings' => $this->qualityWarnings($data['html']),
            'design_brief' => $this->designBrief($owner->project_id, $data['owner_type'], $data['owner_id']),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'owner_type' => $schema->string()->enum(['work_item', 'requirement'])->description('The spec entity the mockup belongs to')->required(),
            'owner_id' => $schema->string()->description('ULID of the work item or requirement that owns the mockup')->required(),
            'name' => $schema->string()->description('Optional label for the mockup. Omit to update the owner\'s single default mockup in place; pass a value to hold a named alternative alongside the default.'),
            'html' => $schema->string()->description('A self-contained HTML document — the mockup. Inline styles, scripts, and assets; external URLs are accepted but returned with quality warnings because durable mockups should not depend on outside resources. Read the owner\'s mockup design brief first; if the artifact diverges from that brief, make the mismatch visible in the mockup or its notes.')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'owner_type' => $schema->string()->required(),
            'owner_id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'revision' => $schema->integer()->description('Number of the revision this call appended')->required(),
            'created' => $schema->boolean()->description('Whether this call created the mockup')->required(),
            'warnings' => $schema->array()->description('Non-blocking quality warnings for HTML patterns that often make weak or brittle mockups')->required(),
            'design_brief' => $schema->object()->description('Brief resource to read before generating or refining this mockup')->required(),
        ];
    }

    /**
     * @return list<array{code:string,message:string}>
     */
    private function qualityWarnings(string $html): array
    {
        $warnings = [];

        if ($this->containsExternalAssets($html)) {
            $warnings[] = [
                'code' => 'external_assets',
                'message' => 'Mockup HTML references external scripts, styles, or assets. Keep mockups self-contained with inline CSS/JS and embedded assets when possible.',
            ];
        }

        if ($this->containsWholeScreenStatePicker($html)) {
            $warnings[] = [
                'code' => 'whole_screen_state_picker',
                'message' => 'Mockup HTML appears to include a screen/state picker that swaps whole screens. Prefer separate named mockups for materially different states, and reserve local JavaScript for natural interactions.',
            ];
        }

        if ($this->containsLocalDesignSystemCss($html)) {
            $warnings[] = [
                'code' => 'local_design_system_css',
                'message' => 'Mockup HTML appears to embed a reusable mini design system. Keep mockup CSS structural and move repeated visual styling, theme tokens, and component chrome into Growth theme raw_css, CSS tokens, and design notes.',
            ];
        }

        return $warnings;
    }

    private function containsExternalAssets(string $html): bool
    {
        return preg_match('/<(?:script|link|img|iframe|video|audio|source)\b[^>]+\b(?:src|href)\s*=\s*["\'](?:https?:)?\/\//i', $html) === 1
            || preg_match('/url\(\s*["\']?(?:https?:)?\/\//i', $html) === 1;
    }

    private function containsWholeScreenStatePicker(string $html): bool
    {
        $hasStateSelector = preg_match('/<(?:select|nav|fieldset|div|section)\b[^>]*(?:state|screen|view)[^>]*>.*?<option\b.*?<option\b/is', $html) === 1
            || preg_match('/<(?:button|a)\b[^>]*(?:data-(?:state|screen|view)|aria-controls)\s*=\s*["\'][^"\']+["\'][^>]*>.*?<\/(?:button|a)>.*?<(?:button|a)\b[^>]*(?:data-(?:state|screen|view)|aria-controls)\s*=/is', $html) === 1;

        if (! $hasStateSelector) {
            return false;
        }

        $hasWholeScreenPanels = preg_match('/\b(?:class|id)\s*=\s*["\'][^"\']*(?:screen|state-panel|view-panel|mockup-state)[^"\']*["\']/i', $html) === 1;
        $hasSwapScript = preg_match('/(?:querySelectorAll|getElementById|classList|style\.display|hidden\s*=|\.hidden)/i', $html) === 1;

        return $hasWholeScreenPanels && $hasSwapScript;
    }

    private function containsLocalDesignSystemCss(string $html): bool
    {
        preg_match_all('/<style\b[^>]*>(?P<css>.*?)<\/style>/is', $html, $matches);
        $css = trim(implode("\n", $matches['css'] ?? []));

        if ($css === '') {
            return false;
        }

        $selectorHits = 0;
        foreach (['.card', '.panel', '.grid', '.badge', '.button', '.btn', 'button', 'table', 'th', 'td', 'header', 'nav'] as $selector) {
            if (preg_match('/(^|[,{]\s*)'.preg_quote($selector, '/').'(?:\s|[,{:#.\[])/im', $css) === 1) {
                $selectorHits++;
            }
        }

        $tokenLikeDeclarations = preg_match_all('/--[a-z0-9_-]+\s*:/i', $css);
        $visualDeclarations = preg_match_all('/\b(?:background|color|border|box-shadow|font|border-radius|padding|margin|gap)\s*:/i', $css);

        return $selectorHits >= 5
            || ($selectorHits >= 3 && $tokenLikeDeclarations >= 3)
            || ($selectorHits >= 3 && $visualDeclarations >= 14);
    }

    /**
     * @return array{uri:string,guidance:string,architecture_available:bool,architecture_views:list<array{id:string,viewpoint:string,name:string,elements_count:int}>}
     */
    private function designBrief(string $projectId, string $ownerType, string $ownerId): array
    {
        $views = DesignView::query()
            ->where('project_id', $projectId)
            ->withCount('elements')
            ->orderBy('viewpoint')
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'project_id', 'viewpoint', 'name']);

        return [
            'uri' => "growth://owners/{$ownerType}/{$ownerId}/mockup-design-brief",
            'guidance' => $views->isNotEmpty()
                ? 'Read the mockup design brief before generating HTML; it bundles requirements, existing mockups, and architecture context for this owner.'
                : 'Read the mockup design brief before generating HTML; no architecture views are captured for this project yet, but owner requirements and existing mockups may still matter.',
            'architecture_available' => $views->isNotEmpty(),
            'architecture_views' => $views->map(fn (DesignView $view): array => [
                'id' => $view->id,
                'viewpoint' => $view->viewpoint,
                'name' => $view->name,
                'elements_count' => $view->elements_count,
            ])->all(),
        ];
    }
}
