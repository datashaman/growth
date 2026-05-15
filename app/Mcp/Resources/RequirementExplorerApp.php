<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Attributes\AppMeta;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;

#[Name('Requirement Explorer')]
#[Description('Interactive requirement browser: filter requirements by layer/type/priority, drill into acceptance checks, sources, derived design/test/work-item links, and requirement lint findings.')]
#[Uri('ui://resources/requirement-explorer')]
#[AppMeta]
class RequirementExplorerApp extends AppResource
{
    public function handle(Request $request): Response
    {
        return Response::view('mcp.requirement-explorer-app', [
            'title' => $this->title(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedAppMeta(): array
    {
        $appMeta = parent::resolvedAppMeta();

        unset($appMeta['domain']);

        return $appMeta;
    }
}
