<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Reviews\UpsertReviewFinding as BaseUpsertReviewFinding;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create or update a finding from a review, including disposition, owner, due date, and target artifact.')]
class UpsertReviewFinding extends BaseUpsertReviewFinding {}
