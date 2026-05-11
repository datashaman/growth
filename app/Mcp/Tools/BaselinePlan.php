<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\BaselinePlan as BaseBaselinePlan;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create an immutable baseline snapshot of the current delivery plan and work state.')]
class BaselinePlan extends BaseBaselinePlan {}
