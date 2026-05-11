<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\ListAgents as BaseListAgents;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('List agents registered in a project, filterable by kind and name.')]
class ListAgents extends BaseListAgents {}
