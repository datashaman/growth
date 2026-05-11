<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\UnlinkWorkItemDependency as BaseUnlinkWorkItemDependency;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Drop a dependency edge between two work items.')]
class UnlinkWorkItemDependency extends BaseUnlinkWorkItemDependency {}
