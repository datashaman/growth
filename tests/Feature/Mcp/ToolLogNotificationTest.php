<?php

use App\Growth\Logging\LogLevel;
use App\Mcp\McpLogReporter;
use App\Mcp\Tools\Assurance\BuildEvidenceBundle;
use App\Mcp\Tools\Assurance\ScanContradictions;
use App\Mcp\Tools\Manifest\ApplyManifest;
use App\Mcp\Tools\Manifest\ExportManifest;
use App\Models\Project;
use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Logging\Logging;
use Laravel\Mcp\Server\Notifications\ProgressNotification;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
});

function logTestProject(): Project
{
    return Project::create([
        'workspace_id' => test()->user->active_workspace_id,
        'name' => 'Logging Project',
        'rigor_level' => 2,
        'status' => 'active',
    ]);
}

/**
 * Invoke a tool's generator handler to completion and return the decoded
 * `notifications/message` records the run emitted to the transport.
 *
 * @param  array<string,mixed>  $args
 * @return list<array<string,mixed>>
 */
function drainLogTool(Tool $tool, array $args, bool $enabled, string $threshold = 'debug'): array
{
    $transport = new FakeTransporter;
    $logging = new Logging($transport, enabled: $enabled, threshold: $threshold);

    iterator_to_array($tool->handle(new Request($args), new ProgressNotification($transport), $logging));

    return array_values(array_filter(
        array_map(
            fn (string $json): array => json_decode($json, true),
            $transport->sentNotifications(),
        ),
        fn (array $message): bool => $message['method'] === 'notifications/message',
    ));
}

/**
 * Assert a captured log stream is well-formed: each record is a
 * `notifications/message` carrying a recognised level and a string message.
 *
 * @param  list<array<string,mixed>>  $records
 */
function assertWellFormedLog(array $records, int $expectedCount): void
{
    expect($records)->toHaveCount($expectedCount);

    foreach ($records as $record) {
        expect($record['method'])->toBe('notifications/message')
            ->and(LogLevel::tryFrom($record['params']['level']))->not->toBeNull()
            ->and($record['params']['data']['message'])->toBeString();
    }
}

it('streams a notifications/message per scan phase for scan-contradictions', function () {
    $project = logTestProject();
    $tool = app(ScanContradictions::class);

    // Three per-check records plus one run summary.
    assertWellFormedLog(drainLogTool($tool, ['project_id' => $project->id], enabled: true), 4);

    expect(drainLogTool($tool, ['project_id' => $project->id], enabled: false))->toBeEmpty();
});

it('emits a structured record per readiness gate for build-evidence-bundle', function () {
    $project = logTestProject();
    $tool = app(BuildEvidenceBundle::class);

    assertWellFormedLog(drainLogTool($tool, ['project_id' => $project->id], enabled: true), 7);

    expect(drainLogTool($tool, ['project_id' => $project->id], enabled: false))->toBeEmpty();
});

it('emits a structured record per manifest section for export-manifest', function () {
    $project = logTestProject();
    $tool = app(ExportManifest::class);

    assertWellFormedLog(drainLogTool($tool, ['project_id' => $project->id], enabled: true), 7);

    expect(drainLogTool($tool, ['project_id' => $project->id], enabled: false))->toBeEmpty();
});

it('emits a structured record per manifest section for apply-manifest', function () {
    $tool = app(ApplyManifest::class);

    assertWellFormedLog(
        drainLogTool($tool, ['manifest' => ['project' => ['name' => 'Apply With Logging']]], enabled: true),
        7,
    );

    expect(drainLogTool($tool, ['manifest' => ['project' => ['name' => 'Apply No Logging']]], enabled: false))
        ->toBeEmpty();
});

it('drops records below the client-negotiated log level', function () {
    $project = logTestProject();
    $tool = app(ScanContradictions::class);

    // A contradiction-free scan emits only `info` records, so a `warning`
    // threshold drops every one of them.
    expect(drainLogTool($tool, ['project_id' => $project->id], enabled: true, threshold: 'warning'))
        ->toBeEmpty();

    expect(drainLogTool($tool, ['project_id' => $project->id], enabled: true, threshold: 'debug'))
        ->toHaveCount(4);
});

it('forwards level, message, and context through McpLogReporter', function () {
    $transport = new FakeTransporter;
    $reporter = new McpLogReporter(new Logging($transport, enabled: true, threshold: 'debug'));

    $reporter->log(LogLevel::Warning, 'Something noteworthy', ['count' => 3]);

    $record = json_decode($transport->sentNotifications()[0], true);

    expect($record['method'])->toBe('notifications/message')
        ->and($record['params']['level'])->toBe('warning')
        ->and($record['params']['data'])->toBe(['message' => 'Something noteworthy', 'context' => ['count' => 3]]);
});

it('advertises the logging capability on surface servers', function () {
    $capabilities = $this->postJson('/mcp/readonly', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-11-25',
            'clientInfo' => ['name' => 'test', 'version' => '1.0.0'],
            'capabilities' => [],
        ],
    ])->assertOk()->json('result.capabilities');

    expect($capabilities)->toHaveKey('logging');
});

it('sends nothing through McpLogReporter when logging is disabled', function () {
    $transport = new FakeTransporter;
    $reporter = new McpLogReporter(new Logging($transport, enabled: false));

    $reporter->log(LogLevel::Error, 'Should not be sent');

    expect($transport->sentNotifications())->toBeEmpty();
});
