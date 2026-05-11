<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Attributes\AppMeta;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('Project Dashboard App')]
#[Description('Interactive read-only project dashboard for Growth readiness, delivery, and traceability state.')]
#[AppMeta]
class ProjectDashboardApp extends AppResource
{
    public function handle(Request $request): Response
    {
        return Response::view('mcp.project-dashboard-app', [
            'title' => $this->title(),
        ]);
    }
}
