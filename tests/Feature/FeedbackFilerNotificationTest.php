<?php

use App\Growth\Transitions\ReopenFeedback;
use App\Growth\Transitions\ResolveFeedback;
use App\Growth\Transitions\TriageFeedback;
use App\Models\ToolFeedback;
use App\Models\User;
use App\Notifications\FeedbackStatusChanged;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->actor = User::factory()->create();
    $this->filer = User::factory()->create();

    $this->makeFeedback = fn (string $status, ?User $filer = null): ToolFeedback => ToolFeedback::create([
        'workspace_id' => $this->filer->active_workspace_id,
        'user_id' => ($filer ?? $this->filer)?->id,
        'category' => 'suggestion',
        'status' => $status,
        'summary' => 'list-risks should accept a project slug',
        'body' => 'Passing a slug instead of an id would be friendlier.',
    ]);
});

test('triaging feedback notifies the filer', function () {
    $feedback = ($this->makeFeedback)('new');

    Notification::fake();

    (new TriageFeedback)->apply($feedback, $this->actor);

    Notification::assertSentTo($this->filer, FeedbackStatusChanged::class);
});

test('resolving feedback notifies the filer', function () {
    $feedback = ($this->makeFeedback)('triaged');

    Notification::fake();

    (new ResolveFeedback)->apply($feedback, $this->actor);

    Notification::assertSentTo($this->filer, FeedbackStatusChanged::class);
});

test('reopening feedback notifies the filer', function () {
    $feedback = ($this->makeFeedback)('resolved');

    Notification::fake();

    (new ReopenFeedback)->apply($feedback, $this->actor);

    Notification::assertSentTo($this->filer, FeedbackStatusChanged::class);
});

test('no notification when the filer transitions their own feedback', function () {
    $feedback = ($this->makeFeedback)('new', $this->filer);

    Notification::fake();

    (new TriageFeedback)->apply($feedback, $this->filer);

    Notification::assertNothingSent();
});

test('no notification when the feedback was filed by an agent', function () {
    $feedback = ToolFeedback::create([
        'workspace_id' => $this->filer->active_workspace_id,
        'user_id' => null,
        'category' => 'suggestion',
        'status' => 'new',
        'summary' => 'An agent filed this',
        'body' => 'No user account behind it.',
    ]);

    Notification::fake();

    (new TriageFeedback)->apply($feedback, $this->actor);

    Notification::assertNothingSent();
});

test('the notification names the new status and links to the feedback', function () {
    $feedback = ($this->makeFeedback)('triaged');

    (new ResolveFeedback)->apply($feedback, $this->actor);

    $data = $this->filer->notifications()->firstOrFail()->data;

    expect($data['event'])->toBe('feedback.status_changed')
        ->and($data['title'])->toBe('Feedback resolved')
        ->and($data['subject_type'])->toBe('feedback')
        ->and($data['subject_id'])->toBe($feedback->id)
        ->and($data['url'])->toBe(route('feedback', [], false));
});

test('a reopened feedback notification reads "reopened"', function () {
    $feedback = ($this->makeFeedback)('resolved');

    (new ReopenFeedback)->apply($feedback, $this->actor);

    expect($this->filer->notifications()->firstOrFail()->data['title'])->toBe('Feedback reopened');
});
