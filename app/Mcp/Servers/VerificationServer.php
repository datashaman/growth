<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\CheckReadiness;
use App\Mcp\Resources\CapabilitiesResource;
use App\Mcp\Resources\EvidenceResource;
use App\Mcp\Resources\PlaybookResource;
use App\Mcp\Resources\ProjectIndexResource;
use App\Mcp\Resources\ReadinessResource;
use App\Mcp\Resources\RigorLevelsResource;
use App\Mcp\Resources\VerificationResource;
use App\Mcp\Servers\Concerns\RoleServerDefaults;
use App\Mcp\Tools\Assurance\ReportEvidenceGaps;
use App\Mcp\Tools\BuildEvidenceBundle;
use App\Mcp\Tools\DeleteAnomaly;
use App\Mcp\Tools\DeleteVerificationCase;
use App\Mcp\Tools\DeleteVerificationPlan;
use App\Mcp\Tools\DeleteVerificationRun;
use App\Mcp\Tools\EvaluateReadinessGates;
use App\Mcp\Tools\LintVerification;
use App\Mcp\Tools\ListAnomalies;
use App\Mcp\Tools\ListCapabilities;
use App\Mcp\Tools\ListCheckRuns;
use App\Mcp\Tools\ListProjects;
use App\Mcp\Tools\ListVerificationCases;
use App\Mcp\Tools\ListVerificationPlans;
use App\Mcp\Tools\ListVerificationRuns;
use App\Mcp\Tools\LogVerificationRun;
use App\Mcp\Tools\Trace\TraceQuery;
use App\Mcp\Tools\UpsertAnomaly;
use App\Mcp\Tools\UpsertCheckRun;
use App\Mcp\Tools\UpsertVerificationCases;
use App\Mcp\Tools\UpsertVerificationPlan;
use App\Mcp\Tools\WhoAmI;
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
        ListProjects::class,
        ListCapabilities::class,
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
        UpsertCheckRun::class,
        ListCheckRuns::class,
        LintVerification::class,
        EvaluateReadinessGates::class,
        BuildEvidenceBundle::class,
        ReportEvidenceGaps::class,
        TraceQuery::class,
    ];

    protected array $resources = [
        PlaybookResource::class,
        RigorLevelsResource::class,
        ProjectIndexResource::class,
        CapabilitiesResource::class,
        VerificationResource::class,
        EvidenceResource::class,
        ReadinessResource::class,
    ];

    protected array $prompts = [
        CheckReadiness::class,
    ];
}
