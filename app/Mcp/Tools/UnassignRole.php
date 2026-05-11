<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\UnassignRole as BaseUnassignRole;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Remove a role assignment from a user or agent.')]
class UnassignRole extends BaseUnassignRole {}
