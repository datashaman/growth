<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\CheckReadiness;
use App\Mcp\Resources\EvidenceResource;
use App\Mcp\Resources\PlaybookResource;
use App\Mcp\Resources\ProjectIndexResource;
use App\Mcp\Resources\ReadinessResource;
use App\Mcp\Resources\RequirementsResource;
use App\Mcp\Resources\RigorLevelsResource;
use App\Mcp\Resources\VerificationResource;
use App\Mcp\Servers\Concerns\RoleServerDefaults;
use App\Mcp\Tools\Assurance\BuildEvidenceBundle;
use App\Mcp\Tools\Assurance\EvaluateReadinessGates;
use App\Mcp\Tools\Assurance\ReportEvidenceGaps;
use App\Mcp\Tools\Common\WhoAmI;
use App\Mcp\Tools\Feedback\SearchFeedback;
use App\Mcp\Tools\Feedback\SendFeedback;
use App\Mcp\Tools\Lint\LintProject;
use App\Mcp\Tools\Plan\ListCheckRuns;
use App\Mcp\Tools\Plan\UpsertCheckRun;
use App\Mcp\Tools\Projects\ListProjects;
use App\Mcp\Tools\Requirements\ListRequirements;
use App\Mcp\Tools\Trace\TraceQuery;
use App\Mcp\Tools\Verification\CloseAnomaly;
use App\Mcp\Tools\Verification\DeleteAnomaly;
use App\Mcp\Tools\Verification\DeleteVerificationCase;
use App\Mcp\Tools\Verification\DeleteVerificationPlan;
use App\Mcp\Tools\Verification\DeleteVerificationRun;
use App\Mcp\Tools\Verification\ListAnomalies;
use App\Mcp\Tools\Verification\ListVerificationCases;
use App\Mcp\Tools\Verification\ListVerificationPlans;
use App\Mcp\Tools\Verification\ListVerificationRuns;
use App\Mcp\Tools\Verification\LogVerificationRun;
use App\Mcp\Tools\Verification\ReopenAnomaly;
use App\Mcp\Tools\Verification\ResolveAnomaly;
use App\Mcp\Tools\Verification\StartAnomalyInvestigation;
use App\Mcp\Tools\Verification\UpsertAnomaly;
use App\Mcp\Tools\Verification\UpsertVerificationCases;
use App\Mcp\Tools\Verification\UpsertVerificationPlan;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Verification Server')]
#[Version('0.1.0')]
#[Instructions('Capture verification plans, cases, runs, anomalies, check evidence, and readiness.')]
class VerificationServer extends Server
{
    use RoleServerDefaults;

    protected array $tools = [
        WhoAmI::class,
        SearchFeedback::class,
        SendFeedback::class,
        ListProjects::class,
        ListRequirements::class,
        UpsertVerificationPlan::class,
        ListVerificationPlans::class,
        DeleteVerificationPlan::class,
        UpsertVerificationCases::class,
        ListVerificationCases::class,
        DeleteVerificationCase::class,
        LogVerificationRun::class,
        ListVerificationRuns::class,
        DeleteVerificationRun::class,
        UpsertAnomaly::class,
        ListAnomalies::class,
        DeleteAnomaly::class,
        StartAnomalyInvestigation::class,
        ResolveAnomaly::class,
        CloseAnomaly::class,
        ReopenAnomaly::class,
        UpsertCheckRun::class,
        ListCheckRuns::class,
        EvaluateReadinessGates::class,
        BuildEvidenceBundle::class,
        ReportEvidenceGaps::class,
        LintProject::class,
        TraceQuery::class,
    ];

    protected array $resources = [
        PlaybookResource::class,
        RigorLevelsResource::class,
        ProjectIndexResource::class,
        RequirementsResource::class,
        VerificationResource::class,
        EvidenceResource::class,
        ReadinessResource::class,
    ];

    protected array $prompts = [
        CheckReadiness::class,
    ];
}
