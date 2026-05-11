<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\DeleteAgent as BaseDeleteAgent;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Delete an agent. Role assignments for this agent are removed; roles remain.')]
class DeleteAgent extends BaseDeleteAgent {}
