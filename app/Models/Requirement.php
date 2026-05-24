<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsProjectChanges;
use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Requirement extends Model
{
    use BroadcastsProjectChanges;
    use HasUlids;
    use ScopedByOwner;

    protected $fillable = [
        'project_id', 'parent_id', 'number', 'slug', 'doc', 'type', 'text',
        'rationale', 'acceptance_criteria', 'source', 'priority', 'tags',
        'renders_ui',
    ];

    protected $casts = [
        'number' => 'integer',
        'acceptance_criteria' => 'array',
        'tags' => 'array',
        'renders_ui' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $requirement): void {
            if ($requirement->number === null) {
                $requirement->number = $requirement->nextNumberForDoc();
            }

            if ($requirement->slug === null || $requirement->slug === '') {
                $requirement->slug = self::deriveUniqueSlug($requirement->project_id, (string) $requirement->text);
            }
        });

        // The reference embeds the document tier (e.g. "SRS-001"), so moving a
        // requirement to another tier must re-number it within that tier —
        // otherwise the stale number can collide on the (project, doc, number)
        // unique index and the reference would misreport the tier.
        static::updating(function (self $requirement): void {
            if ($requirement->isDirty('doc')) {
                $requirement->number = $requirement->nextNumberForDoc();
            }
        });
    }

    /**
     * Human-readable per-document reference, e.g. "SRS-001". The document tier
     * is the prefix, so each tier numbers independently within a project.
     */
    public function reference(): string
    {
        return strtoupper($this->doc).'-'.str_pad((string) $this->number, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Allocate the next sequential number within this requirement's document
     * tier. Callers that may run concurrently should wrap the create in a
     * transaction so the project row lock held here spans the insert.
     */
    protected function nextNumberForDoc(): int
    {
        Project::whereKey($this->project_id)->lockForUpdate()->first();

        return (int) static::where('project_id', $this->project_id)
            ->where('doc', $this->doc)
            ->max('number') + 1;
    }

    public static function deriveUniqueSlug(string $projectId, string $text, ?string $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($text), 100, '');
        if ($base === '') {
            $base = 'requirement';
        }
        $slug = $base;
        $n = 2;
        while (DB::table('requirements')
            ->where('project_id', $projectId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.$n;
            $n++;
        }

        return $slug;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function testCases(): BelongsToMany
    {
        return $this->belongsToMany(TestCase::class, 'requirement_test_case');
    }

    public function anomalies(): BelongsToMany
    {
        return $this->belongsToMany(Anomaly::class, 'anomaly_requirement');
    }

    public function citations(): MorphMany
    {
        return $this->morphMany(Citation::class, 'citable');
    }

    public function workItems(): BelongsToMany
    {
        return $this->belongsToMany(WorkItem::class, 'requirement_work_item');
    }

    public function mockups(): MorphMany
    {
        return $this->morphMany(Mockup::class, 'owner');
    }

    public function reviewTargets(): MorphMany
    {
        return $this->morphMany(ReviewTarget::class, 'reviewable');
    }

    public function changeImpacts(): MorphMany
    {
        return $this->morphMany(ChangeImpact::class, 'impactable');
    }
}
