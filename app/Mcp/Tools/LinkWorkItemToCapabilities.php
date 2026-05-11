<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\LinkWorkItemToRequirements as BaseLinkWorkItemToRequirements;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Link a work item to one or more capabilities it delivers. Idempotent.')]
class LinkWorkItemToCapabilities extends BaseLinkWorkItemToRequirements {}
