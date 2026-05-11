<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Reviews\UpsertReviewParticipant as BaseUpsertReviewParticipant;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create or update a participant assignment for a review, including responsibility, attendance, signoff, and notes.')]
class UpsertReviewParticipant extends BaseUpsertReviewParticipant {}
