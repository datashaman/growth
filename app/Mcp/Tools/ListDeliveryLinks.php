<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\ListWorkItemDeliveryLinks as BaseListWorkItemDeliveryLinks;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('List delivery evidence links for one work item or all work items in a project.')]
class ListDeliveryLinks extends BaseListWorkItemDeliveryLinks {}
