<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\UnlinkWorkItemFromRequirement as BaseUnlinkWorkItemFromRequirement;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Drop the link between a work item and one capability. Neither artifact is deleted.')]
class UnlinkWorkItemFromCapability extends BaseUnlinkWorkItemFromRequirement {}
