<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\SummarizeScheduleHealth as BaseSummarizeScheduleHealth;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Summarize schedule health and dependency risk from milestones, due dates, and work dependencies.')]
class SummarizeScheduleHealth extends BaseSummarizeScheduleHealth {}
