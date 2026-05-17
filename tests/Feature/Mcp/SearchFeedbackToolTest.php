<?php

use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Feedback\SearchFeedback;
use App\Models\ToolFeedback;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->makeFeedback = fn (array $attributes = []): ToolFeedback => ToolFeedback::create(array_merge([
        'workspace_id' => $this->user->active_workspace_id,
        'user_id' => $this->user->id,
        'category' => 'difficulty',
        'status' => 'new',
        'summary' => 'Generic summary',
        'body' => 'Generic body',
    ], $attributes));
});

it('matches the free-text query against summary and body', function () {
    ($this->makeFeedback)(['summary' => 'Pagination is unclear']);
    ($this->makeFeedback)(['summary' => 'Other', 'body' => 'The offset behaviour is unclear']);
    ($this->makeFeedback)(['summary' => 'Unrelated', 'body' => 'Unrelated']);

    ReadonlyServer::tool(SearchFeedback::class, ['query' => 'unclear'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('total', 2)->etc();
        });
});

it('filters by category, tool_name, and status', function () {
    ($this->makeFeedback)(['category' => 'bug', 'tool_name' => 'upsert-risk', 'status' => 'triaged']);
    ($this->makeFeedback)(['category' => 'bug', 'tool_name' => 'list-risks', 'status' => 'new']);
    ($this->makeFeedback)(['category' => 'suggestion', 'tool_name' => 'upsert-risk', 'status' => 'triaged']);

    ReadonlyServer::tool(SearchFeedback::class, ['category' => 'bug'])
        ->assertStructuredContent(fn ($json) => $json->where('total', 2)->etc());

    ReadonlyServer::tool(SearchFeedback::class, ['tool_name' => 'upsert-risk'])
        ->assertStructuredContent(fn ($json) => $json->where('total', 2)->etc());

    ReadonlyServer::tool(SearchFeedback::class, ['status' => 'new'])
        ->assertStructuredContent(fn ($json) => $json->where('total', 1)->etc());
});

it('reports pagination metadata', function () {
    ($this->makeFeedback)();
    ($this->makeFeedback)();
    ($this->makeFeedback)();

    ReadonlyServer::tool(SearchFeedback::class, ['limit' => 2, 'offset' => 1])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('total', 3)
                ->where('limit', 2)
                ->where('offset', 1)
                ->has('results', 2)
                ->etc();
        });
});

it('only returns feedback from the active workspace', function () {
    ($this->makeFeedback)(['summary' => 'Local feedback']);

    $other = User::factory()->create();
    ToolFeedback::create([
        'workspace_id' => $other->active_workspace_id,
        'user_id' => $other->id,
        'category' => 'difficulty',
        'status' => 'new',
        'summary' => 'Foreign feedback',
        'body' => 'Body',
    ]);

    ReadonlyServer::tool(SearchFeedback::class, [])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('total', 1)->etc();
        });
});
