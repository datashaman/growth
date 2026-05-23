<?php

namespace App\Support;

use App\Models\Requirement;
use App\Models\SpecMockup;
use App\Models\Theme;
use App\Models\WorkItem;

class MockupThemeResolver
{
    /**
     * @return array{requested:string,resolved:string,slug:?string,id:?string,name:?string}
     */
    public function resolve(SpecMockup $mockup, string $requestedTheme): array
    {
        $requestedTheme = trim($requestedTheme) === '' ? 'assigned' : trim($requestedTheme);

        if ($requestedTheme === 'none') {
            return [
                'requested' => 'none',
                'resolved' => 'none',
                'slug' => null,
                'id' => null,
                'name' => null,
            ];
        }

        $project = $mockup->owner?->project;

        if (! $project) {
            return [
                'requested' => $requestedTheme,
                'resolved' => 'none',
                'slug' => null,
                'id' => null,
                'name' => null,
            ];
        }

        $theme = $requestedTheme === 'assigned'
            ? $this->assignedTheme($mockup)
            : Theme::query()
                ->where('project_id', $project->id)
                ->where('slug', $requestedTheme)
                ->first();

        if (! $theme) {
            return [
                'requested' => $requestedTheme,
                'resolved' => 'none',
                'slug' => null,
                'id' => null,
                'name' => null,
            ];
        }

        return [
            'requested' => $requestedTheme,
            'resolved' => 'theme',
            'slug' => $theme->slug,
            'id' => $theme->id,
            'name' => $theme->name,
        ];
    }

    private function assignedTheme(SpecMockup $mockup): ?Theme
    {
        $owner = $mockup->owner;
        $project = $owner?->project;

        if (! $project) {
            return null;
        }

        $keys = match (true) {
            $owner instanceof WorkItem => [
                ['mockup', $mockup->id],
                ['work_item', $owner->id],
                ['work_item', $owner->reference()],
            ],
            $owner instanceof Requirement => [
                ['mockup', $mockup->id],
                ['requirement', $owner->id],
                ['requirement', $owner->reference()],
            ],
            default => [
                ['mockup', $mockup->id],
            ],
        };

        $assignment = null;

        foreach ($keys as [$scopeType, $scopeKey]) {
            $assignment = $project->themeAssignments
                ->first(fn ($assignment): bool => $assignment->scope_type === $scopeType && $assignment->scope_key === $scopeKey);

            if ($assignment !== null) {
                break;
            }
        }

        return $assignment?->theme ?? $project->themes->firstWhere('is_default', true);
    }
}
