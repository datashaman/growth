<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Report the authenticated user for this session plus session-grounding context: owned-project count, the most recently touched owned project, and every role the user holds across projects. Returns `{authenticated: false}` for anonymous local sessions without GROWTH_USER_EMAIL or GROWTH_USER_ID. Useful as a one-call replacement for `list-projects` + `list-roles` when an agent is orienting itself.')]
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
                'owned_projects' => 0,
                'last_touched_project' => null,
                'roles' => [],
            ]);
        }

        $ownedProjects = Project::where('user_id', $user->id)->count();

        $lastTouched = Project::where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->first(['id', 'name']);

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
            'owned_projects' => $ownedProjects,
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
            'user_id' => $schema->integer(),
            'email' => $schema->string(),
            'name' => $schema->string(),
            'owned_projects' => $schema->integer()->required(),
            'last_touched_project' => $schema->object(),
            'roles' => $schema->array()->required(),
        ];
    }
}
