<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Reviews\UpsertReview as BaseUpsertReview;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create or update a review record with optional target artifacts, decision state, and summary.')]
class UpsertReview extends BaseUpsertReview {}
