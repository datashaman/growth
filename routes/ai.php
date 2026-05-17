<?php

use App\Mcp\Servers\AllServer;
use App\Mcp\Servers\ArchitectureServer;
use App\Mcp\Servers\GovernanceServer;
use App\Mcp\Servers\IntakeServer;
use App\Mcp\Servers\ManagementServer;
use App\Mcp\Servers\PlanningServer;
use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Servers\VerificationServer;
use Laravel\Mcp\Facades\Mcp;

// Local stdio transport — runs under the user's own shell session. Set
// GROWTH_USER_EMAIL or GROWTH_USER_ID to bind the trusted local process to an
// application user for owner-scoped tools. Set GROWTH_ROLE to bind the session
// to an operating role (#183) — it must match the role server it connects to,
// and it takes effect only once a user is bound via GROWTH_USER_EMAIL/_ID.
Mcp::local('all', AllServer::class);
Mcp::local('management', ManagementServer::class);
Mcp::local('intake', IntakeServer::class);
Mcp::local('architecture', ArchitectureServer::class);
Mcp::local('planning', PlanningServer::class);
Mcp::local('verification', VerificationServer::class);
Mcp::local('governance', GovernanceServer::class);
Mcp::local('readonly', ReadonlyServer::class);

Mcp::oauthRoutes();

// HTTP transport — guarded by Passport OAuth access tokens.
// The OAuth discovery routes advertise the MCP resource metadata and `mcp:use`
// scope expected by MCP clients.
Mcp::web('/mcp/all', AllServer::class)->middleware('auth:api');
Mcp::web('/mcp/management', ManagementServer::class)->middleware('auth:api');
Mcp::web('/mcp/intake', IntakeServer::class)->middleware('auth:api');
Mcp::web('/mcp/architecture', ArchitectureServer::class)->middleware('auth:api');
Mcp::web('/mcp/planning', PlanningServer::class)->middleware('auth:api');
Mcp::web('/mcp/verification', VerificationServer::class)->middleware('auth:api');
Mcp::web('/mcp/governance', GovernanceServer::class)->middleware('auth:api');
Mcp::web('/mcp/readonly', ReadonlyServer::class)->middleware('auth:api');
