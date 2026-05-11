<?php

use App\Models\Anomaly;
use App\Models\Citation;
use App\Models\Concern;
use App\Models\CustomViewpoint;
use App\Models\DesignView;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Source;
use App\Models\TestCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->alice = User::factory()->create();
    $this->bob = User::factory()->create();
    $this->aliceProject = Project::create([
        'user_id' => $this->alice->id,
        'name' => 'Apollo',
        'integrity_level' => 2,
    ]);
    $this->bobProject = Project::create([
        'user_id' => $this->bob->id,
        'name' => 'Bob Project',
        'integrity_level' => 2,
    ]);
});

it('registers the morph map with stable short names', function () {
    $map = Relation::morphMap();

    expect($map)->toHaveKeys([
        'requirement', 'concern', 'design_view',
        'custom_viewpoint', 'test_case', 'anomaly',
    ]);
    expect($map['requirement'])->toBe(Requirement::class);
});

it('cascades citations when their source is deleted', function () {
    $source = Source::create([
        'project_id' => $this->aliceProject->id,
        'kind' => 'brief',
        'title' => 'Kickoff brief',
        'body' => 'The system should...',
    ]);
    $req = Requirement::create([
        'project_id' => $this->aliceProject->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The system shall log every authentication attempt.',
    ]);
    Citation::create([
        'source_id' => $source->id,
        'citable_type' => 'requirement',
        'citable_id' => $req->id,
        'quote' => 'system should log',
        'locator' => '§2.1',
    ]);

    expect(Citation::count())->toBe(1);

    $source->delete();

    expect(Citation::count())->toBe(0);
    expect(Requirement::withoutGlobalScopes()->find($req->id))->not->toBeNull();
});

it('cascades sources when their project is deleted', function () {
    Source::create([
        'project_id' => $this->aliceProject->id,
        'kind' => 'rfp',
        'title' => 'RFP',
    ]);

    $this->aliceProject->delete();

    expect(Source::withoutGlobalScopes()->count())->toBe(0);
});

it('scopes sources to the authenticated owner', function () {
    Source::create([
        'project_id' => $this->aliceProject->id,
        'kind' => 'brief',
        'title' => 'Alice source',
    ]);
    Source::create([
        'project_id' => $this->bobProject->id,
        'kind' => 'brief',
        'title' => 'Bob source',
    ]);

    auth()->login($this->alice);

    expect(Source::count())->toBe(1);
    expect(Source::first()->title)->toBe('Alice source');
});

it('scopes citations to the authenticated owner via source', function () {
    $aliceSource = Source::create([
        'project_id' => $this->aliceProject->id,
        'kind' => 'brief',
        'title' => 'Alice',
    ]);
    $bobSource = Source::create([
        'project_id' => $this->bobProject->id,
        'kind' => 'brief',
        'title' => 'Bob',
    ]);
    $aliceReq = Requirement::create([
        'project_id' => $this->aliceProject->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'Alice requirement.',
    ]);
    $bobReq = Requirement::create([
        'project_id' => $this->bobProject->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'Bob requirement.',
    ]);
    Citation::create([
        'source_id' => $aliceSource->id,
        'citable_type' => 'requirement',
        'citable_id' => $aliceReq->id,
    ]);
    Citation::create([
        'source_id' => $bobSource->id,
        'citable_type' => 'requirement',
        'citable_id' => $bobReq->id,
    ]);

    auth()->login($this->alice);

    expect(Citation::count())->toBe(1);
    expect(Citation::first()->source_id)->toBe($aliceSource->id);
});

it('walks the polymorphic citations relation on a requirement', function () {
    $source = Source::create([
        'project_id' => $this->aliceProject->id,
        'kind' => 'brief',
        'title' => 'Brief',
    ]);
    $req = Requirement::create([
        'project_id' => $this->aliceProject->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'A requirement.',
    ]);
    Citation::create([
        'source_id' => $source->id,
        'citable_type' => 'requirement',
        'citable_id' => $req->id,
        'locator' => '§1',
    ]);

    expect($req->citations()->count())->toBe(1);
    expect($req->citations->first()->source_id)->toBe($source->id);
});

it('enforces the unique citation index on duplicate insertion', function () {
    $source = Source::create([
        'project_id' => $this->aliceProject->id,
        'kind' => 'brief',
        'title' => 'Brief',
    ]);
    $req = Requirement::create([
        'project_id' => $this->aliceProject->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'A requirement.',
    ]);

    Citation::create([
        'source_id' => $source->id,
        'citable_type' => 'requirement',
        'citable_id' => $req->id,
        'locator' => '§1',
    ]);

    expect(fn () => Citation::create([
        'source_id' => $source->id,
        'citable_type' => 'requirement',
        'citable_id' => $req->id,
        'locator' => '§1',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('allows multiple citations from the same source with different locators', function () {
    $source = Source::create([
        'project_id' => $this->aliceProject->id,
        'kind' => 'brief',
        'title' => 'Brief',
    ]);
    $req = Requirement::create([
        'project_id' => $this->aliceProject->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'A requirement.',
    ]);
    Citation::create([
        'source_id' => $source->id,
        'citable_type' => 'requirement',
        'citable_id' => $req->id,
        'locator' => '§1',
    ]);
    Citation::create([
        'source_id' => $source->id,
        'citable_type' => 'requirement',
        'citable_id' => $req->id,
        'locator' => '§2',
    ]);

    expect($req->citations()->count())->toBe(2);
});

it('exposes citations() on each of the six citable models', function () {
    $models = [
        Requirement::class,
        Concern::class,
        DesignView::class,
        CustomViewpoint::class,
        TestCase::class,
        Anomaly::class,
    ];

    foreach ($models as $class) {
        $instance = new $class;
        expect(method_exists($instance, 'citations'))->toBeTrue();
        expect($instance->citations())
            ->toBeInstanceOf(MorphMany::class);
    }
});
