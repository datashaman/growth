<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\SummarizePlanCapacity as BaseSummarizePlanCapacity;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Summarize effort, capacity, utilization, and numeric cost by responsible role.')]
class SummarizePlanCapacity extends BaseSummarizePlanCapacity {}
