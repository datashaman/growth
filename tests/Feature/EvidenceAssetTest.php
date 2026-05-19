<?php

use App\Models\EvidenceAsset;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemDeliveryLink;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;

beforeEach(function () {
    Storage::fake('s3');

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);

    $this->workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'name' => 'Ship the lander',
        'kind' => 'task',
        'status' => 'in_progress',
    ]);

    $this->deliveryLink = WorkItemDeliveryLink::create([
        'work_item_id' => $this->workItem->id,
        'type' => 'evidence',
        'ref' => 'PR-1',
    ]);
});

/**
 * Create an evidence asset with its backing object present on the fake disk.
 */
function storedEvidenceAsset(WorkItemDeliveryLink $link, string $path, string $caption): EvidenceAsset
{
    Storage::disk(EvidenceAsset::DISK)->put($path, 'fake-png-bytes');

    return $link->evidenceAssets()->create([
        'path' => $path,
        'caption' => $caption,
        'content_type' => 'image/png',
    ]);
}

test('an authenticated upload stores the images and returns public urls', function () {
    Passport::actingAs($this->user, ['mcp:use']);

    $response = $this->post(route('evidence-assets.store'), [
        'delivery_link_id' => $this->deliveryLink->id,
        'images' => [UploadedFile::fake()->image('home.png', 12, 12)],
        'captions' => ['home page'],
    ], ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('assets.0.caption', 'home page')
        ->assertJsonPath('assets.0.content_type', 'image/png');

    $asset = EvidenceAsset::query()->sole();
    expect($asset->work_item_delivery_link_id)->toBe($this->deliveryLink->id);
    expect($response->json('assets.0.url'))->toBe($asset->publicUrl());
    Storage::disk('s3')->assertExists($asset->path);
});

test('the upload endpoint rejects an unauthenticated request', function () {
    $response = $this->post(route('evidence-assets.store'), [
        'delivery_link_id' => $this->deliveryLink->id,
        'images' => [UploadedFile::fake()->image('home.png')],
        'captions' => ['home page'],
    ], ['Accept' => 'application/json']);

    $response->assertUnauthorized();
    expect(EvidenceAsset::query()->count())->toBe(0);
});

test('the upload endpoint rejects a non-png file', function () {
    Passport::actingAs($this->user, ['mcp:use']);

    $response = $this->post(route('evidence-assets.store'), [
        'delivery_link_id' => $this->deliveryLink->id,
        'images' => [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')],
        'captions' => ['a document'],
    ], ['Accept' => 'application/json']);

    $response->assertStatus(422);
    expect(EvidenceAsset::query()->count())->toBe(0);
});

test('a stored evidence image is served publicly with the png content type', function () {
    $asset = storedEvidenceAsset($this->deliveryLink, 'evidence-assets/shot.png', 'home');

    // No authentication — the route is public so GitHub's camo proxy can fetch it.
    $response = $this->get(route('evidence-assets.show', $asset));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('image/png');
});

test('serving a missing evidence object returns 404', function () {
    $asset = $this->deliveryLink->evidenceAssets()->create([
        'path' => 'evidence-assets/gone.png',
        'caption' => 'gone',
        'content_type' => 'image/png',
    ]);

    $this->get(route('evidence-assets.show', $asset))->assertNotFound();
});

test('re-uploading replaces the delivery link existing assets and their objects', function () {
    Passport::actingAs($this->user, ['mcp:use']);

    $this->post(route('evidence-assets.store'), [
        'delivery_link_id' => $this->deliveryLink->id,
        'images' => [UploadedFile::fake()->image('first.png')],
        'captions' => ['first run'],
    ], ['Accept' => 'application/json'])->assertCreated();

    $firstPath = EvidenceAsset::query()->sole()->path;

    $this->post(route('evidence-assets.store'), [
        'delivery_link_id' => $this->deliveryLink->id,
        'images' => [UploadedFile::fake()->image('second.png')],
        'captions' => ['second run'],
    ], ['Accept' => 'application/json'])->assertCreated();

    $remaining = EvidenceAsset::query()->get();
    expect($remaining)->toHaveCount(1);
    expect($remaining->first()->caption)->toBe('second run');
    Storage::disk('s3')->assertMissing($firstPath);
});

test('deleting the work item removes its evidence assets and their s3 objects', function () {
    $asset = storedEvidenceAsset($this->deliveryLink, 'evidence-assets/wi.png', 'shot');

    $this->workItem->delete();

    $this->assertDatabaseMissing('evidence_assets', ['id' => $asset->id]);
    Storage::disk('s3')->assertMissing($asset->path);
});

test('deleting the project removes evidence assets and their s3 objects', function () {
    $asset = storedEvidenceAsset($this->deliveryLink, 'evidence-assets/proj.png', 'shot');

    $this->project->delete();

    $this->assertDatabaseMissing('evidence_assets', ['id' => $asset->id]);
    Storage::disk('s3')->assertMissing($asset->path);
});
