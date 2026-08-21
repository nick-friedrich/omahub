<?php

namespace App\Enums;

use Illuminate\Support\Collection;

enum RiskLevel: string
{
    case None = 'none';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    /**
     * The aggregate risk level for a collection of findings, weighted by the
     * most severe finding present.
     */
    public static function aggregate(array|Collection $from): self
    {
        $levels = Collection::wrap($from);

        $order = array_flip(array_map(fn (self $level) => $level->value, self::cases()));

        $worstRank = $levels
            ->map(fn (self $level) => $order[$level->value])
            ->max();

        if ($worstRank === null) {
            return self::None;
        }

        return self::cases()[$worstRank];
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
