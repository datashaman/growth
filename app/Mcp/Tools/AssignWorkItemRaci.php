<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\AssignWorkItemRaci as BaseAssignWorkItemRaci;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Assign a role to a work item under a responsibility label. Idempotent.')]
class AssignWorkItemRaci extends BaseAssignWorkItemRaci {}
