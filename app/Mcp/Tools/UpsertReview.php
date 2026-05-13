<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Reviews\UpsertReview as BaseUpsertReview;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create or update a review record with optional target artifacts, decision state, and summary. The response includes a missing_prerequisites list summarising which lint-reviews readiness checks the review will currently fail (targets, participants, entry/exit criteria, inspection roles, review-plan expected responsibilities) so you can address them before running lint-reviews.')]
class UpsertReview extends BaseUpsertReview {}
