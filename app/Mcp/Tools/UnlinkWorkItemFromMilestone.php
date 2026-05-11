<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\UnlinkWorkItemFromMilestone as BaseUnlinkWorkItemFromMilestone;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Drop the link between a work item and a milestone. Neither artifact is deleted.')]
class UnlinkWorkItemFromMilestone extends BaseUnlinkWorkItemFromMilestone {}
