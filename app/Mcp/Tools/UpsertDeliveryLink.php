<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\UpsertWorkItemDeliveryLink as BaseUpsertWorkItemDeliveryLink;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create or update a delivery evidence link from a work item to a commit, pull request, branch, or external artifact.')]
class UpsertDeliveryLink extends BaseUpsertWorkItemDeliveryLink {}
