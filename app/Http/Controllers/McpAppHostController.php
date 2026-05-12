<?php

namespace App\Http\Controllers;

use App\Mcp\Resources\ArchitectureResource;
use App\Mcp\Resources\CapabilitiesResource;
use App\Mcp\Resources\EvidenceResource;
use App\Mcp\Resources\IntentResource;
use App\Mcp\Resources\PlanResource;
use App\Mcp\Resources\ProjectDashboardApp;
use App\Mcp\Resources\ProjectIndexResource;
use App\Mcp\Resources\ReadinessResource;
use App\Mcp\Resources\VerificationResource;
use App\Mcp\Tools\GetProjectDashboardData;
use App\Mcp\Tools\ShowProjectDashboard;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;
use Throwable;

class McpAppHostController extends Controller
{
    /**
     * @var array<string, class-string<Tool>>
     */
    private const TOOLS = [
        'get-project-dashboard-data' => GetProjectDashboardData::class,
        'show-project-dashboard' => ShowProjectDashboard::class,
    ];

    /**
     * @var array<int, class-string<resource>>
     */
    private const RESOURCES = [
        ProjectIndexResource::class,
        IntentResource::class,
        CapabilitiesResource::class,
        ArchitectureResource::class,
        VerificationResource::class,
        PlanResource::class,
        EvidenceResource::class,
        ReadinessResource::class,
    ];

    public function showProjectDashboard(ProjectDashboardApp $app): View
    {
        $this->authenticateLocalViewer();

        app()->instance('mcp.library_scripts', $app->libraryScripts());

        try {
            $html = (string) $app->handle(new McpRequest)->content();
        } finally {
            app()->forgetInstance('mcp.library_scripts');
        }

        return view('mcp.app-host', [
            'title' => 'Growth Project Dashboard',
            'appHtml' => $html,
        ]);
    }

    public function rpc(HttpRequest $request): JsonResponse
    {
        $this->authenticateLocalViewer();

        $message = $request->validate([
            'id' => 'required',
            'method' => 'required|string',
            'params' => 'nullable|array',
        ]);

        try {
            $result = match ($message['method']) {
                'tools/call' => $this->callTool($message['params'] ?? []),
                'resources/list' => $this->listResources(),
                'resources/read' => $this->readResource($message['params'] ?? []),
                'ui/message', 'ui/open-link', 'ui/update-model-context', 'ui/request-display-mode' => (object) [],
                default => throw new \InvalidArgumentException("Method [{$message['method']}] is not supported by this local app host."),
            };

            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $message['id'],
                'result' => $result,
            ]);
        } catch (Throwable $throwable) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $message['id'],
                'error' => [
                    'code' => -32603,
                    'message' => $throwable->getMessage(),
                ],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function listResources(): array
    {
        return [
            'resources' => collect(self::RESOURCES)
                ->map(fn (string $resource): array => app($resource)->toArray())
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function readResource(array $params): array
    {
        $uri = $params['uri'] ?? null;
        if (! is_string($uri) || $uri === '') {
            throw new \InvalidArgumentException('Missing [uri] parameter.');
        }

        $resource = collect(self::RESOURCES)
            ->map(fn (string $resource): Resource => app($resource))
            ->first(function (Resource $resource) use ($uri): bool {
                if ($resource->uri() === $uri) {
                    return true;
                }

                return $resource instanceof HasUriTemplate
                    && $resource->uriTemplate()->match($uri) !== null;
            });

        if (! $resource) {
            throw new \InvalidArgumentException("Resource [{$uri}] is not available in this local app host.");
        }

        $mcpRequest = new McpRequest(uri: $uri);
        if ($resource instanceof HasUriTemplate) {
            $mcpRequest->merge($resource->uriTemplate()->match($uri) ?? []);
        }

        app()->instance(McpRequest::class, $mcpRequest);

        try {
            $response = app()->call([$resource, 'handle']);
        } finally {
            app()->forgetInstance(McpRequest::class);
        }

        if (! $response instanceof Response) {
            throw new \InvalidArgumentException('Resource handlers must return a response.');
        }

        return [
            'contents' => [
                [
                    ...$response->content()->toResource($resource),
                    'uri' => $uri,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function callTool(array $params): array
    {
        $name = $params['name'] ?? null;
        if (! is_string($name) || ! isset(self::TOOLS[$name])) {
            throw new \InvalidArgumentException("Tool [{$name}] is not available in this local app host.");
        }

        $arguments = $params['arguments'] ?? [];
        if (! is_array($arguments)) {
            throw new \InvalidArgumentException('Tool arguments must be an object.');
        }

        $tool = app(self::TOOLS[$name]);
        app()->instance(McpRequest::class, new McpRequest($arguments));

        try {
            return $this->serializeToolResponse($tool, app()->call([$tool, 'handle']));
        } finally {
            app()->forgetInstance(McpRequest::class);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeToolResponse(Tool $tool, Response|ResponseFactory $response): array
    {
        $factory = $response instanceof ResponseFactory
            ? $response
            : new ResponseFactory($response);

        $payload = [
            'content' => $factory->responses()
                ->map(fn (Response $response): array => $response->content()->toTool($tool))
                ->all(),
            'isError' => $factory->responses()
                ->contains(fn (Response $response): bool => $response->isError()),
        ];

        if ($factory->getStructuredContent() !== null) {
            $payload['structuredContent'] = $factory->getStructuredContent();
        }

        if ($factory->getMeta() !== null) {
            $payload['_meta'] = $factory->getMeta();
        }

        return $payload;
    }

    private function authenticateLocalViewer(): void
    {
        if (Auth::check()) {
            return;
        }

        if (($userId = (string) env('GROWTH_USER_ID', '')) !== '') {
            if (($user = User::find($userId)) !== null) {
                Auth::login($user);
            }

            return;
        }

        if (($email = (string) env('GROWTH_USER_EMAIL', '')) !== '') {
            if (($user = User::where('email', $email)->first()) !== null) {
                Auth::login($user);
            }

            return;
        }

    }
}
