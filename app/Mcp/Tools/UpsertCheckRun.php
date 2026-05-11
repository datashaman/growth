<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\UpsertCheckRunEvidence as BaseUpsertCheckRunEvidence;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create or update check-run evidence for a delivery link.')]
class UpsertCheckRun extends BaseUpsertCheckRunEvidence {}
