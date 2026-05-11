<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\UpsertAgent as BaseUpsertAgent;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create or update an automated or specialized agent that can fill project roles.')]
class UpsertAgent extends BaseUpsertAgent {}
