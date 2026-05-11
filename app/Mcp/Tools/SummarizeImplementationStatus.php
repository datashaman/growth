<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\SummarizeImplementationStatus as BaseSummarizeImplementationStatus;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Summarize implementation status across work items, delivery evidence, checks, and deployments.')]
class SummarizeImplementationStatus extends BaseSummarizeImplementationStatus {}
