<?php

use App\Mcp\Tools\Feedback\TriageFeedback;
use App\Models\StatusTransition;
use App\Models\ToolFeedback;
use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Sampling\Sampling;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->feedback = ToolFeedback::create([
        'workspace_id' => $this->user->active_workspace_id,
        'user_id' => $this->user->id,
        'category' => 'bug',
        'status' => 'new',
        'summary' => 'list-risks returns a 500',
        'body' => 'Calling list-risks without a project_id throws.',
    ]);
});

/**
 * A `sampling/createMessage` result envelope carrying a single text block.
 *
 * @return array<string,mixed>
 */
function triageSamplingResult(string $text): array
{
    return ['role' => 'assistant', 'content' => ['type' => 'text', 'text' => $text]];
}

/**
 * Invoke `triage-feedback` directly with a sampling channel backed by a
 * fake transport, and return that transport for inspection.
 *
 * @param  array<string,mixed>  $args
 * @param  array<string,mixed>  $clientCapabilities
 */
function invokeTriageWithSampling(array $args, array $clientCapabilities, ?string $queuedText): FakeTransporter
{
    $transport = new FakeTransporter;

    if ($queuedText !== null) {
        $transport->expectResponse(triageSamplingResult($queuedText));
    }

    $sampling = new Sampling($transport, $clientCapabilities);

    app(TriageFeedback::class)->handle(new Request(['feedback_id' => test()->feedback->id, ...$args]), $sampling);

    return $transport;
}

it('drafts a triage rationale via sampling when no reason is supplied', function () {
    $transport = invokeTriageWithSampling([], ['sampling' => []], 'The 500 is a missing project_id guard; routine bug.');

    $requests = $transport->sentRequests();
    expect($requests)->toHaveCount(1)
        ->and($requests[0]['method'])->toBe('sampling/createMessage')
        ->and($requests[0]['params']['maxTokens'])->toBe(200)
        ->and($requests[0]['params']['systemPrompt'])->toBeString()
        ->and($requests[0]['params']['messages'][0]['content']['text'])->toContain('list-risks returns a 500');

    $transition = StatusTransition::query()->sole();
    expect($transition->to_status)->toBe('triaged')
        ->and($transition->reason)->toContain('The 500 is a missing project_id guard; routine bug.')
        ->and($transition->reason)->toContain('drafted via MCP sampling');
});

it('records a caller-supplied reason verbatim without sampling', function () {
    $transport = invokeTriageWithSampling(
        ['reason' => 'Reproduced locally.'],
        ['sampling' => []],
        'Drafted text that must never be used.',
    );

    expect($transport->sentRequests())->toBeEmpty()
        ->and(StatusTransition::query()->sole()->reason)->toBe('Reproduced locally.');
});

it('triages without a reason when the client cannot sample', function () {
    $transport = invokeTriageWithSampling([], [], null);

    expect($transport->sentRequests())->toBeEmpty();

    $transition = StatusTransition::query()->sole();
    expect($transition->to_status)->toBe('triaged')
        ->and($transition->reason)->toBeNull();
});
