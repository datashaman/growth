<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Attributes\AppMeta;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;

#[Name('Trace Graph')]
#[Description('Interactive traceability graph: pick a project + starting artifact (capability or any artifact ID) and visualize the trace-query nodes and edges with adjustable depth and direction.')]
#[Uri('ui://resources/trace-graph')]
#[AppMeta]
class TraceGraphApp extends AppResource
{
    public function handle(Request $request): Response
    {
        return Response::view('mcp.trace-graph-app', [
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
