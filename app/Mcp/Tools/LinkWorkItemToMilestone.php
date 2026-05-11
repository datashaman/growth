<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\LinkWorkItemToMilestone as BaseLinkWorkItemToMilestone;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Link a work item to a milestone. Idempotent.')]
class LinkWorkItemToMilestone extends BaseLinkWorkItemToMilestone {}
