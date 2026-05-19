<?php

use App\Mcp\Tools\Projects\DeleteProject;
use App\Models\Project;
use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Elicitation\Elicitation;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Disposable',
        'rigor_level' => 2,
    ]);
});

/**
 * An `elicitation/create` result envelope for a form-mode confirmation.
 *
 * @return array<string,mixed>
 */
function deleteElicitationResult(string $action, ?bool $confirm = null): array
{
    $result = ['action' => $action];

    if ($confirm !== null) {
        $result['content'] = ['confirm' => $confirm];
    }

    return $result;
}

/**
 * Invoke `delete-project` directly with an elicitation channel backed by a
 * fake transport, and return that transport for inspection.
 *
 * @param  array<string,mixed>  $args
 * @param  array<string,mixed>  $clientCapabilities
 */
function invokeDeleteWithElicitation(array $args, array $clientCapabilities, ?array $queuedResult): FakeTransporter
{
    $transport = new FakeTransporter;

    if ($queuedResult !== null) {
        $transport->expectElicitation($queuedResult);
    }

    $elicitation = new Elicitation($transport, $clientCapabilities);

    app(DeleteProject::class)->handle(
        new Request(['id' => test()->project->id, ...$args]),
        $elicitation,
    );

    return $transport;
}

it('deletes the project when the client confirms via elicitation', function () {
    $transport = invokeDeleteWithElicitation([], ['elicitation' => []], deleteElicitationResult('accept', true));

    $elicitations = $transport->sentElicitations();
    expect($elicitations)->toHaveCount(1)
        ->and($elicitations[0]['params']['message'])->toContain('Disposable')
        ->and($elicitations[0]['params']['requestedSchema']['properties'])->toHaveKey('confirm');

    expect(Project::find(test()->project->id))->toBeNull();
});

it('keeps the project when the client declines the elicitation', function () {
    $transport = invokeDeleteWithElicitation([], ['elicitation' => []], deleteElicitationResult('decline'));

    expect($transport->sentElicitations())->toHaveCount(1)
        ->and(Project::find(test()->project->id))->not->toBeNull();
});

it('keeps the project when the form is accepted but confirmation is unchecked', function () {
    invokeDeleteWithElicitation([], ['elicitation' => []], deleteElicitationResult('accept', false));

    expect(Project::find(test()->project->id))->not->toBeNull();
});

it('keeps the project when an accepted form carries no confirmation field', function () {
    invokeDeleteWithElicitation([], ['elicitation' => []], deleteElicitationResult('accept'));

    expect(Project::find(test()->project->id))->not->toBeNull();
});

it('deletes via confirm_name without eliciting', function () {
    $transport = invokeDeleteWithElicitation(['confirm_name' => 'Disposable'], ['elicitation' => []], null);

    expect($transport->sentRequests())->toBeEmpty()
        ->and(Project::find(test()->project->id))->toBeNull();
});

it('keeps the project when the client cannot elicit and no confirm_name is given', function () {
    $transport = invokeDeleteWithElicitation([], [], null);

    expect($transport->sentRequests())->toBeEmpty()
        ->and(Project::find(test()->project->id))->not->toBeNull();
});
