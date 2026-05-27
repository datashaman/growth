<?php

namespace App\Mcp\Tools\Common;

use App\Models\Project;
use App\Models\User;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Report the authenticated user for this session, the active workspace, and every workspace the user belongs to (with role). Includes project count + most recently touched project inside the active workspace, and every RACI role the user holds across projects in that workspace. Returns `{authenticated: false}` for anonymous local sessions without GROWTH_USER_EMAIL or GROWTH_USER_ID.')]
class WhoAmI extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        /** @var User|null $user */
        $user = auth()->user();

        if ($user === null) {
            return Response::structured([
                'authenticated' => false,
                'user_id' => null,
                'email' => null,
                'name' => null,
                'active_workspace' => null,
                'workspaces' => [],
                'projects_in_workspace' => 0,
                'last_touched_project' => null,
                'roles' => [],
            ]);
        }

        $workspaceId = app(WorkspaceContext::class)->id();

        $workspaces = $user->workspaces()
            ->orderBy('name')
            ->get(['workspaces.id', 'workspaces.name', 'workspaces.slug'])
            ->map(fn ($workspace) => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'role' => $workspace->pivot->role,
                'is_active' => $workspace->id === $workspaceId,
            ])
            ->all();

        $activeWorkspace = collect($workspaces)->firstWhere('is_active');

        $projectsInWorkspace = Project::count();

        $lastTouched = Project::orderByDesc('updated_at')->first(['id', 'name']);

        $roles = $user->roles()
            ->with('project:id,name')
            ->get(['roles.id', 'roles.name', 'roles.project_id'])
            ->map(fn ($role) => [
                'role_id' => $role->id,
                'name' => $role->name,
                'project_id' => $role->project_id,
                'project_name' => $role->project?->name,
            ])
            ->all();

        return Response::structured([
            'authenticated' => true,
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'active_workspace' => $activeWorkspace,
            'workspaces' => $workspaces,
            'projects_in_workspace' => $projectsInWorkspace,
            'last_touched_project' => $lastTouched
                ? ['id' => $lastTouched->id, 'name' => $lastTouched->name]
                : null,
            'roles' => $roles,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'authenticated' => $schema->boolean()->required(),
            'user_id' => $schema->integer()
                ->description('Integer id of the authenticated user. Pass this as the assign-roles `assignee_id` (with assignee_type=user) to self-assign project roles.'),
            'email' => $schema->string(),
            'name' => $schema->string(),
            'active_workspace' => $schema->object(),
            'workspaces' => $schema->array()->required(),
            'projects_in_workspace' => $schema->integer()->required(),
            'last_touched_project' => $schema->object(),
            'roles' => $schema->array()->required(),
        ];
    }
}
