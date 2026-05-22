<?php

namespace App\Support;

use Illuminate\Support\Collection;

class Lens
{
    /**
     * @param  list<Capability>  $capabilities
     */
    public function __construct(private readonly array $capabilities) {}

    public static function all(): self
    {
        return new self(Capability::cases());
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param  iterable<Capability>  $capabilities
     */
    public static function fromCapabilities(iterable $capabilities): self
    {
        return new self(Collection::make($capabilities)
            ->unique(fn (Capability $capability): string => $capability->value)
            ->values()
            ->all());
    }

    /**
     * @return list<string>
     */
    public function sections(): array
    {
        return Collection::make($this->capabilities)
            ->flatMap(fn (Capability $capability): array => $capability->sections())
            ->unique()
            ->values()
            ->all();
    }

    public function reveals(string $section): bool
    {
        return in_array($section, $this->sections(), true);
    }

    /**
     * True when the Lens reveals no sections at all — every capability-gated
     * nav item is hidden. Used to explain an empty Project nav in-product.
     */
    public function revealsNothing(): bool
    {
        return $this->sections() === [];
    }

    /**
     * @return list<string>
     */
    public function panels(): array
    {
        return Collection::make($this->capabilities)
            ->flatMap(fn (Capability $capability): array => $capability->panels())
            ->unique()
            ->values()
            ->all();
    }

    public function revealsPanel(string $panel): bool
    {
        return in_array($panel, $this->panels(), true);
    }
}
