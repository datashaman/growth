<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\ListPlanBaselines as BaseListPlanBaselines;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('List delivery plan baselines for a plan, newest first.')]
class ListPlanBaselines extends BaseListPlanBaselines {}
