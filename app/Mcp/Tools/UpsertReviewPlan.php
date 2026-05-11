<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Reviews\UpsertReviewPlan as BaseUpsertReviewPlan;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create or update a reusable review plan with objective, procedure, entry and exit criteria, responsibilities, and checklist.')]
class UpsertReviewPlan extends BaseUpsertReviewPlan {}
