<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Completions\CompletionResponse;
use Laravel\Mcp\Server\Contracts\Completable;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('start-project')]
#[Description('Start a Growth project by creating the project, capturing intent, and turning it into initial requirements.')]
class StartProject extends Prompt implements Completable
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function complete(string $argument, string $value, array $context): CompletionResponse
    {
        if ($argument === 'rigor_level') {
            return CompletionResponse::match(['1', '2', '3', '4']);
        }

        return CompletionResponse::empty();
    }

    public function arguments(): array
    {
        return [
            new Argument(
                name: 'name',
                description: 'Working project name.',
                required: true,
            ),
            new Argument(
                name: 'summary',
                description: 'Short description of the product, workflow, or change.',
                required: false,
            ),
            new Argument(
                name: 'rigor_level',
                description: 'Rigor level from 1 to 4. Defaults to 2.',
                required: false,
            ),
        ];
    }

    /**
     * @return array<int, Response>
     */
    public function handle(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'summary' => 'nullable|string|max:2000',
            'rigor_level' => 'nullable|integer|between:1,4',
        ]);

        $name = $data['name'];
        $summary = $data['summary'] ?? 'No summary provided yet.';
        $rigorLevel = $data['rigor_level'] ?? 2;

        $system = <<<'MD'
You are helping the user start a Growth project. Work from intent to evidence:

Fast path (preferred for greenfield): read `growth://template/rigor-N` matching the target rigor, fill in TODO placeholders from the user's summary, then call `apply-manifest` once. This creates the project plus its stakeholders, concerns, requirements, architecture view, plan, and verification plan/case in a single transaction. For L3+, follow up with `baseline-plan` and `upsert-review`.

Manual path (only when the manifest doesn't fit):

1. Create the project with `upsert-project`.
2. Capture stakeholders and concerns with `upsert-stakeholder` and `upsert-concerns`.
3. Add sources with `upsert-source` when the user provides briefs, links, transcripts, tickets, or docs.
4. Convert intent into requirements with `upsert-requirements`.

In both paths, read `growth://playbook` and `growth://projects/{id}` as the project takes shape.

Keep the first turn narrow: pick the path, then ask for the smallest missing intent needed to fill in the manifest or define the first requirements.
MD;

        $user = <<<MD
Start a Growth project named "{$name}" at rigor level {$rigorLevel}.

Summary:
{$summary}
MD;

        return [
            Response::text($system)->asAssistant(),
            Response::text($user),
        ];
    }
}
