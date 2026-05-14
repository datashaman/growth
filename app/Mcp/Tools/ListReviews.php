<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Reviews\ListReviews as BaseListReviews;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('List review records for a project, filterable by type, status, decision, owner role, and substring. For findings, participants, and decision events tied to a review, use `trace-query` with the review id.')]
class ListReviews extends BaseListReviews {}
