<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Attributes\AppMeta;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;

#[Name('Gate Status')]
#[Description('Interactive readiness gate status board: shows pass/warn/fail per gate (requirements, architecture, verification, planning, review, change control, implementation) with the blocking findings.')]
#[Uri('ui://resources/gate-status')]
#[AppMeta]
class GateStatusApp extends AppResource
{
    public function handle(Request $request): Response
    {
        return Response::view('mcp.gate-status-app', [
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
