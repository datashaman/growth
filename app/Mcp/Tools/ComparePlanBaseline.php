<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\ComparePlanBaseline as BaseComparePlanBaseline;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Compare a delivery plan baseline snapshot to the current plan and work state.')]
class ComparePlanBaseline extends BaseComparePlanBaseline {}
