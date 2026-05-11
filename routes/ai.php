<?php

use App\Mcp\Servers\AllServer;
use App\Mcp\Servers\ArchitectureServer;
use App\Mcp\Servers\GovernanceServer;
use App\Mcp\Servers\IntakeServer;
use App\Mcp\Servers\PlanningServer;
use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Servers\VerificationServer;
use Laravel\Mcp\Facades\Mcp;

// Local stdio transport — runs under the user's own shell session. Set
// GROWTH_USER_EMAIL or GROWTH_USER_ID to bind the trusted local process to an
// application user for owner-scoped tools.
Mcp::local('all', AllServer::class);
Mcp::local('intake', IntakeServer::class);
Mcp::local('architecture', ArchitectureServer::class);
Mcp::local('planning', PlanningServer::class);
Mcp::local('verification', VerificationServer::class);
Mcp::local('governance', GovernanceServer::class);
Mcp::local('readonly', ReadonlyServer::class);

// HTTP transport — guarded by Sanctum personal access tokens.
// Clients send `Authorization: Bearer <token>`; the authenticated user
// scopes every project/child lookup through the model global scopes.
Mcp::web('/mcp/all', AllServer::class)->middleware('auth:sanctum');
Mcp::web('/mcp/intake', IntakeServer::class)->middleware('auth:sanctum');
Mcp::web('/mcp/architecture', ArchitectureServer::class)->middleware('auth:sanctum');
Mcp::web('/mcp/planning', PlanningServer::class)->middleware('auth:sanctum');
Mcp::web('/mcp/verification', VerificationServer::class)->middleware('auth:sanctum');
Mcp::web('/mcp/governance', GovernanceServer::class)->middleware('auth:sanctum');
Mcp::web('/mcp/readonly', ReadonlyServer::class)->middleware('auth:sanctum');
