<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\ListCheckRunEvidence as BaseListCheckRunEvidence;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('List check-run evidence for a project, delivery link, or work item.')]
class ListCheckRuns extends BaseListCheckRunEvidence {}
