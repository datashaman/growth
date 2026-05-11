<?php

namespace App\Growth\Reviews;

use App\Models\Requirement;
use App\Models\Review;
use App\Models\ReviewFinding;

class RequirementReviewCoverage
{
    /**
     * @return array{
     *     requirement_id:string,
     *     review_count:int,
     *     accepted_review_count:int,
     *     open_finding_count:int,
     *     latest_review:array{id:string,title:string,status:string,decision:?string}|null,
     *     covered:bool
     * }
     */
    public function summarize(Requirement $requirement): array
    {
        $reviews = $requirement->reviewTargets()
            ->with('review')
            ->get()
            ->pluck('review')
            ->filter();

        $accepted = $reviews->filter(fn (Review $review): bool => $review->status === 'closed'
            && in_array($review->decision, ['accepted', 'accepted_with_actions'], true));

        $latest = $reviews
            ->sortByDesc(fn (Review $review): string => (string) ($review->held_at ?? $review->planned_at ?? $review->created_at))
            ->first();

        $openFindings = ReviewFinding::query()
            ->where('project_id', $requirement->project_id)
            ->where('reviewable_type', 'requirement')
            ->where('reviewable_id', $requirement->id)
            ->whereNotIn('status', ['resolved', 'accepted', 'closed'])
            ->count();

        return [
            'requirement_id' => $requirement->id,
            'review_count' => $reviews->count(),
            'accepted_review_count' => $accepted->count(),
            'open_finding_count' => $openFindings,
            'latest_review' => $latest ? [
                'id' => $latest->id,
                'title' => $latest->title,
                'status' => $latest->status,
                'decision' => $latest->decision,
            ] : null,
            'covered' => $accepted->isNotEmpty(),
        ];
    }
}
