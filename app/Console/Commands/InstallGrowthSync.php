<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

#[Signature('growth-sync:install {project : Project ULID} {email : Email address of the Growth user the sync should act as} {--growth-url= : Growth base URL. Defaults to APP_URL} {--github-token= : GitHub token with repository Actions secrets/variables write access. Defaults to GITHUB_TOKEN}')]
#[Description('Operator-only setup for growth-sync: issue a workspace-bound MCP token and write it directly into the bound GitHub repository secret without printing it.')]
class InstallGrowthSync extends Command
{
    public function handle(): int
    {
        $project = Project::find($this->argument('project'));
        if (! $project) {
            $this->error("No project found with id [{$this->argument('project')}].");

            return self::FAILURE;
        }

        if ($project->github_repo === null || $project->github_repo === '') {
            $this->error('Project is not bound to a GitHub repository.');

            return self::FAILURE;
        }

        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error("No user found with email [{$this->argument('email')}].");

            return self::FAILURE;
        }

        if (! $user->workspaces()->where('workspaces.id', $project->workspace_id)->exists()) {
            $this->error("User [{$user->email}] does not belong to the project's workspace.");

            return self::FAILURE;
        }

        $githubToken = $this->option('github-token') ?: env('GITHUB_TOKEN');
        if (! is_string($githubToken) || $githubToken === '') {
            $this->error('A GitHub token is required. Pass --github-token or set GITHUB_TOKEN.');

            return self::FAILURE;
        }

        $growthUrl = $this->option('growth-url') ?: config('app.url');
        if (! is_string($growthUrl) || $growthUrl === '') {
            $this->error('A Growth URL is required. Pass --growth-url or set APP_URL.');

            return self::FAILURE;
        }

        $github = $this->github($githubToken);
        $result = null;

        try {
            $this->upsertVariable($github, $project->github_repo, rtrim($growthUrl, '/'));

            $result = $user->createToken('growth-sync:'.$project->github_repo, ['mcp:use']);
            $result->getToken()?->forceFill(['workspace_id' => $project->workspace_id])->save();

            $this->putSecret($github, $project->github_repo, $result->accessToken);
        } catch (\Throwable $exception) {
            $result?->getToken()?->revoke();
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("growth-sync secret and URL variable installed for {$project->github_repo}.");
        $this->comment('The Growth MCP token was generated and sent to GitHub Secrets without being printed.');

        return self::SUCCESS;
    }

    private function github(string $token): PendingRequest
    {
        return Http::baseUrl('https://api.github.com')
            ->accept('application/vnd.github+json')
            ->withToken($token)
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
            ]);
    }

    private function putSecret(PendingRequest $github, string $repo, string $token): void
    {
        $keyResponse = $github->get("/repos/{$repo}/actions/secrets/public-key");
        if (! $keyResponse->successful()) {
            throw new \RuntimeException("Could not fetch GitHub Actions public key for {$repo}: {$keyResponse->status()} {$keyResponse->body()}");
        }

        $key = $keyResponse->json('key');
        $keyId = $keyResponse->json('key_id');
        if (! is_string($key) || ! is_string($keyId)) {
            throw new \RuntimeException("GitHub did not return a usable Actions public key for {$repo}.");
        }

        $encrypted = sodium_crypto_box_seal($token, base64_decode($key));

        $response = $github->put("/repos/{$repo}/actions/secrets/GROWTH_MCP_TOKEN", [
            'encrypted_value' => base64_encode($encrypted),
            'key_id' => $keyId,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Could not write GROWTH_MCP_TOKEN for {$repo}: {$response->status()} {$response->body()}");
        }
    }

    private function upsertVariable(PendingRequest $github, string $repo, string $growthUrl): void
    {
        $create = $github->post("/repos/{$repo}/actions/variables", [
            'name' => 'GROWTH_URL',
            'value' => $growthUrl,
        ]);

        if ($create->status() === 409) {
            $update = $github->patch("/repos/{$repo}/actions/variables/GROWTH_URL", [
                'name' => 'GROWTH_URL',
                'value' => $growthUrl,
            ]);

            if (! $update->successful()) {
                throw new \RuntimeException("Could not update GROWTH_URL for {$repo}: {$update->status()} {$update->body()}");
            }

            return;
        }

        if (! $create->successful()) {
            throw new \RuntimeException("Could not write GROWTH_URL for {$repo}: {$create->status()} {$create->body()}");
        }
    }
}
