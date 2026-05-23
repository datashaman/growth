<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsProjectChanges;
use App\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Theme extends Model
{
    use BroadcastsProjectChanges;
    use HasUlids;

    protected $fillable = [
        'project_id',
        'name',
        'slug',
        'description',
        'design_notes',
        'css_tokens',
        'raw_css',
        'is_default',
    ];

    protected $casts = [
        'css_tokens' => 'array',
        'is_default' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('workspace', function (Builder $query): void {
            $workspaceId = app(WorkspaceContext::class)->id();

            if ($workspaceId !== null) {
                $query->whereHas('project', fn (Builder $project): Builder => $project->where('workspace_id', $workspaceId));
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ThemeAssignment::class);
    }

    public function markDefault(): void
    {
        DB::transaction(function (): void {
            self::query()
                ->where('project_id', $this->project_id)
                ->whereKeyNot($this->getKey())
                ->update(['is_default' => false]);

            $this->forceFill(['is_default' => true])->save();
        });
    }

    public function clearDefault(): void
    {
        $this->forceFill(['is_default' => false])->save();
    }

    public function styleElement(): string
    {
        $css = trim($this->cssForInjection());

        if ($css === '') {
            return '';
        }

        return "<style data-growth-theme=\"{$this->slug}\">\n{$css}\n</style>";
    }

    public function cssForInjection(): string
    {
        $parts = [];
        $tokens = $this->normalizedCssTokens();

        if ($tokens !== []) {
            $lines = [':root {'];
            foreach ($tokens as $name => $value) {
                $lines[] = "  --{$name}: {$value};";
            }
            $lines[] = '}';
            $parts[] = implode("\n", $lines);
        }

        if (filled($this->raw_css)) {
            $parts[] = trim((string) $this->raw_css);
        }

        return implode("\n\n", $parts);
    }

    /**
     * @return array<string, string>
     */
    public function normalizedCssTokens(): array
    {
        $tokens = is_array($this->css_tokens) ? $this->css_tokens : [];
        $normalized = [];

        foreach ($tokens as $name => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $token = Str::of((string) $name)
                ->trim()
                ->replaceMatches('/^--/', '')
                ->replaceMatches('/[^a-zA-Z0-9_-]+/', '-')
                ->trim('-')
                ->lower()
                ->toString();

            if ($token === '') {
                continue;
            }

            $normalized[$token] = trim((string) $value);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $tokens
     */
    public static function validateSelfContainedCss(?array $tokens, ?string $rawCss): void
    {
        $css = trim(implode("\n", array_filter([
            $rawCss,
            $tokens ? implode("\n", array_map(
                fn (string $name, mixed $value): string => "{$name}: {$value};",
                array_keys($tokens),
                $tokens,
            )) : null,
        ])));

        if ($css === '') {
            return;
        }

        if (preg_match('/@import\b/i', $css) === 1
            || preg_match('/url\(\s*["\']?(?:https?:)?\/\//i', $css) === 1) {
            throw ValidationException::withMessages([
                'raw_css' => 'Theme CSS must be self-contained and cannot import or reference remote assets.',
            ]);
        }
    }
}
