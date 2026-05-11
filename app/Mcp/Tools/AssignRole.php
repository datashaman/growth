<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\AssignRole as BaseAssignRole;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Assign a role to a user or agent. Idempotent.')]
class AssignRole extends BaseAssignRole {}
