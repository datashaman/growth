<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\PlaybookResource;
use App\Mcp\Resources\ProjectIndexResource;
use App\Mcp\Resources\RigorLevelsResource;
use App\Mcp\Resources\StarterTemplate1Resource;
use App\Mcp\Resources\StarterTemplate2Resource;
use App\Mcp\Resources\StarterTemplate3Resource;
use App\Mcp\Resources\StarterTemplate4Resource;
use App\Mcp\Servers\Concerns\RoleServerDefaults;
use App\Mcp\Tools\Common\Doctor;
use App\Mcp\Tools\Common\ListNotifications;
use App\Mcp\Tools\Common\ListUsers;
use App\Mcp\Tools\Common\MarkNotificationRead;
use App\Mcp\Tools\Common\ReplyToNotification;
use App\Mcp\Tools\Common\SendNotification;
use App\Mcp\Tools\Common\WhoAmI;
use App\Mcp\Tools\Decisions\AnswerDecisionRequest;
use App\Mcp\Tools\Decisions\CancelDecisionRequest;
use App\Mcp\Tools\Decisions\CreateDecisionRequest;
use App\Mcp\Tools\Decisions\ListDecisionQueue;
use App\Mcp\Tools\Feedback\CommentFeedback;
use App\Mcp\Tools\Feedback\GetFeedback;
use App\Mcp\Tools\Feedback\ReopenFeedback;
use App\Mcp\Tools\Feedback\ResolveFeedback;
use App\Mcp\Tools\Feedback\SearchFeedback;
use App\Mcp\Tools\Feedback\SendFeedback;
use App\Mcp\Tools\Feedback\TriageFeedback;
use App\Mcp\Tools\Manifest\ApplyManifest;
use App\Mcp\Tools\Manifest\ExportManifest;
use App\Mcp\Tools\Projects\ActivateProject;
use App\Mcp\Tools\Projects\AdoptProject;
use App\Mcp\Tools\Projects\ArchiveProject;
use App\Mcp\Tools\Projects\CloseProject;
use App\Mcp\Tools\Projects\CreateProject;
use App\Mcp\Tools\Projects\DeleteProject;
use App\Mcp\Tools\Projects\ListProjects;
use App\Mcp\Tools\Projects\MoveProject;
use App\Mcp\Tools\Projects\ResolveProjectByRepo;
use App\Mcp\Tools\Projects\RestoreProject;
use App\Mcp\Tools\Projects\ScaffoldGithubSync;
use App\Mcp\Tools\Projects\UpdateProject;
use App\Mcp\Tools\Projects\UpsertProject;
use App\Mcp\Tools\Search\Search;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Management Server')]
#[Version('0.1.0')]
class ManagementServer extends Server
{
    use RoleServerDefaults;

    protected array $tools = [
        WhoAmI::class,
        CreateDecisionRequest::class,
        ListDecisionQueue::class,
        AnswerDecisionRequest::class,
        CancelDecisionRequest::class,
        ListNotifications::class,
        ListUsers::class,
        MarkNotificationRead::class,
        ReplyToNotification::class,
        SendNotification::class,
        Search::class,
        Doctor::class,
        GetFeedback::class,
        SearchFeedback::class,
        CommentFeedback::class,
        SendFeedback::class,
        TriageFeedback::class,
        ResolveFeedback::class,
        ReopenFeedback::class,
        ListProjects::class,
        ResolveProjectByRepo::class,
        ScaffoldGithubSync::class,
        CreateProject::class,
        AdoptProject::class,
        UpdateProject::class,
        UpsertProject::class,
        ActivateProject::class,
        ArchiveProject::class,
        CloseProject::class,
        RestoreProject::class,
        DeleteProject::class,
        MoveProject::class,
        ApplyManifest::class,
        ExportManifest::class,
    ];

    protected array $resources = [
        PlaybookResource::class,
        RigorLevelsResource::class,
        ProjectIndexResource::class,
        StarterTemplate1Resource::class,
        StarterTemplate2Resource::class,
        StarterTemplate3Resource::class,
        StarterTemplate4Resource::class,
    ];
}
