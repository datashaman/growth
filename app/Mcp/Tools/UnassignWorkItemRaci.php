<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\UnassignWorkItemRaci as BaseUnassignWorkItemRaci;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Remove one responsibility assignment from a work item.')]
class UnassignWorkItemRaci extends BaseUnassignWorkItemRaci {}
