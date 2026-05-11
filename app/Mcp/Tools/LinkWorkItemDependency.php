<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\LinkWorkItemDependency as BaseLinkWorkItemDependency;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Declare that one work item depends on another. Idempotent.')]
class LinkWorkItemDependency extends BaseLinkWorkItemDependency {}
