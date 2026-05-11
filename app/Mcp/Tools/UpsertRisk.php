<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\UpsertRisk as BaseUpsertRisk;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create or update a project risk register item. Use owner_role_id for the role accountable for mitigation.')]
class UpsertRisk extends BaseUpsertRisk {}
