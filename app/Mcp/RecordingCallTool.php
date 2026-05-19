<?php

namespace App\Mcp;

use App\Models\ToolInvocation;
use App\Models\Workspace;
use App\Support\AgentContext;
use App\Support\RoleContext;
use App\Support\SurfaceContext;
use App\Support\WorkspaceContext;
use Generator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Server\Methods\CallTool;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Throwable;

class RecordingCallTool extends CallTool
{
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        $startedAt = Carbon::now();
        $startNs = hrtime(true);
        $toolName = is_string($request->params['name'] ?? null) ? $request->params['name'] : null;
        $args = is_array($request->params['arguments'] ?? null) ? $request->params['arguments'] : [];

        try {
            $response = parent::handle($request, $context);
        } catch (Throwable $e) {
            $this->record($toolName, $args, $startedAt, $startNs, false, $e::class, $e->getMessage(), null);
            throw $e;
        }

        // A streamed (generator) tool has not run its body yet — `parent::handle`
        // only built the generator. Wrap it so recording happens once the
        // transport drains the stream, capturing the real result and duration.
        if ($response instanceof Generator) {
            return $this->recordStreamed($response, $toolName, $args, $startedAt, $startNs);
        }

        $resultPayload = $response instanceof JsonRpcResponse ? ($response->toArray()['result'] ?? null) : null;
        $this->finalizeRecording($toolName, $args, $startedAt, $startNs, $resultPayload);

        return $response;
    }

    /**
     * Re-yield a streamed response, then record the invocation once the stream
     * is exhausted — so `duration_ms`, `return_shape`, and error state reflect
     * the tool body that ran lazily as the transport consumed the generator.
     *
     * @param  Generator<JsonRpcResponse>  $stream
     * @param  array<string,mixed>  $args
     * @return Generator<JsonRpcResponse>
     */
    private function recordStreamed(Generator $stream, ?string $toolName, array $args, Carbon $startedAt, int $startNs): Generator
    {
        $last = null;

        try {
            foreach ($stream as $message) {
                $last = $message;
                yield $message;
            }
        } catch (Throwable $e) {
            $this->record($toolName, $args, $startedAt, $startNs, false, $e::class, $e->getMessage(), null);
            throw $e;
        }

        $resultPayload = $last instanceof JsonRpcResponse ? ($last->toArray()['result'] ?? null) : null;
        $this->finalizeRecording($toolName, $args, $startedAt, $startNs, $resultPayload);
    }

    /**
     * Record a completed invocation, deriving success/error state from the
     * final result payload.
     *
     * @param  array<string,mixed>  $args
     * @param  array<string,mixed>|null  $resultPayload
     */
    private function finalizeRecording(?string $toolName, array $args, Carbon $startedAt, int $startNs, mixed $resultPayload): void
    {
        $isError = is_array($resultPayload) && ($resultPayload['isError'] ?? false) === true;

        $this->record(
            $toolName,
            $args,
            $startedAt,
            $startNs,
            ! $isError,
            $isError ? 'tool_error' : null,
            $isError ? $this->extractErrorMessage($resultPayload) : null,
            is_array($resultPayload) ? $resultPayload : null,
        );
    }

    private function record(?string $toolName, array $args, Carbon $startedAt, int $startNs, bool $success, ?string $errorClass, ?string $errorMessage, ?array $resultPayload): void
    {
        try {
            $workspaceId = app(WorkspaceContext::class)->id();
            $userId = auth()->id();
            $agentId = app(AgentContext::class)->id();
            $actingSurface = app(SurfaceContext::class)->surface()?->value;
            $actingRole = app(RoleContext::class)->role();
            $captureFull = $workspaceId !== null && (bool) Workspace::query()->whereKey($workspaceId)->value('mcp_capture_payloads');
            $durationMs = (int) max(0, round((hrtime(true) - $startNs) / 1_000_000));

            ToolInvocation::create([
                'workspace_id' => $workspaceId,
                'user_id' => $userId,
                'agent_id' => $agentId,
                'acting_surface' => $actingSurface,
                'acting_role_id' => $actingRole?->getKey(),
                'acting_role_name' => $actingRole?->name,
                'tool_name' => $toolName ?? 'unknown',
                'transport' => $this->detectTransport(),
                'success' => $success,
                'error_class' => $errorClass,
                'error_message' => $errorMessage,
                'duration_ms' => $durationMs,
                'args_shape' => $this->shape($args),
                'return_shape' => $resultPayload === null ? null : $this->shape($resultPayload),
                'args_full' => $captureFull ? $args : null,
                'return_full' => $captureFull ? $resultPayload : null,
                'started_at' => $startedAt,
                'completed_at' => Carbon::now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to record tool invocation', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Build a JSON-safe summary: keys with their type plus length/count for strings and arrays.
     */
    private function shape(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 3) {
            return ['__truncated__' => true];
        }

        if (is_array($value)) {
            $assoc = array_keys($value) !== range(0, count($value) - 1);
            if ($assoc) {
                $out = [];
                foreach ($value as $k => $v) {
                    $out[(string) $k] = $this->shape($v, $depth + 1);
                }

                return $out;
            }

            return [
                'type' => 'list',
                'count' => count($value),
                'sample' => $value === [] ? null : $this->shape($value[0], $depth + 1),
            ];
        }

        if (is_string($value)) {
            return ['type' => 'string', 'length' => mb_strlen($value)];
        }

        if (is_int($value) || is_float($value)) {
            return ['type' => is_int($value) ? 'integer' : 'float'];
        }

        if (is_bool($value)) {
            return ['type' => 'boolean'];
        }

        if ($value === null) {
            return ['type' => 'null'];
        }

        return ['type' => gettype($value)];
    }

    private function extractErrorMessage(array $resultPayload): ?string
    {
        $content = $resultPayload['content'] ?? null;
        if (! is_array($content)) {
            return null;
        }

        foreach ($content as $item) {
            if (is_array($item) && isset($item['text']) && is_string($item['text'])) {
                return mb_substr($item['text'], 0, 1000);
            }
        }

        return null;
    }

    private function detectTransport(): ?string
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return 'stdio';
        }

        return request()?->isJson() ? 'http' : null;
    }
}
