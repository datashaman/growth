<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A GitHub event growth-sync received but could not attribute to a work
 * item. Keyed by repository string (not work item) because an
 * unattributed event has no work item yet. Pruned aggressively: cleared
 * when the branch is later bound, and old rows are dropped on insert.
 */
class UnattributedGithubEvent extends Model
{
    use HasUlids;

    public const EVENT_TYPES = ['pull_request', 'check_run', 'workflow_run'];

    public const REASONS = ['missing_link', 'ambiguous_branch'];

    /**
     * Events older than this are dropped on insert and hidden from the
     * Evidence page, so the table stays a recent triage net.
     */
    public const RETENTION_DAYS = 30;

    protected $fillable = [
        'github_repo', 'event_type', 'branch', 'commit_sha', 'reason', 'url', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }

    /**
     * Drop the events for a branch once it is bound to a work item — the
     * binding resolves them, so they leave the Evidence exception list.
     */
    public static function clearForBranch(string $githubRepo, string $branch): int
    {
        return static::query()
            ->where('github_repo', $githubRepo)
            ->where('branch', $branch)
            ->delete();
    }

    /**
     * Delete events past the retention window. Keeps the table bounded
     * without a scheduler — callers prune on each insert.
     */
    public static function pruneExpired(): int
    {
        return static::query()
            ->where('received_at', '<', Carbon::now()->subDays(self::RETENTION_DAYS))
            ->delete();
    }

    /**
     * Limit to events still inside the retention window, so the Evidence
     * page never shows stale rows even if no insert has pruned them yet.
     */
    public function scopeWithinRetention(Builder $query): Builder
    {
        return $query->where('received_at', '>=', Carbon::now()->subDays(self::RETENTION_DAYS));
    }
}
