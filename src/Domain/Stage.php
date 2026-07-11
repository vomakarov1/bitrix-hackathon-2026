<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Domain;

/**
 * Стадии эволюции персонажа. Стадия — чистая функция bestStreak; эволюция необратима.
 * Пороги берутся из Constants (STAGE_HATCHLING_AT/BEAST_AT/LEGEND_AT).
 */
enum Stage
{
    case Egg;
    case Hatchling;
    case Beast;
    case Legend;

    /**
     * Чистая фабрика стадии из bestStreak. Пороги — из Constants.
     */
    public static function fromBestStreak(int $bestStreak): self
    {
        return match (true) {
            $bestStreak >= Constants::STAGE_LEGEND_AT => self::Legend,
            $bestStreak >= Constants::STAGE_BEAST_AT => self::Beast,
            $bestStreak >= Constants::STAGE_HATCHLING_AT => self::Hatchling,
            default => self::Egg,
        };
    }

    /** Эмодзи стадии. */
    public function emoji(): string
    {
        return match ($this) {
            self::Egg => '🥚',
            self::Hatchling => '🐣',
            self::Beast => '🦊',
            self::Legend => '🐉',
        };
    }

    /** ASCII-арт стадии. */
    public function art(): string
    {
        return match ($this) {
            self::Egg => '.oOo.',
            self::Hatchling => '(o_o)',
            self::Beast => '(=^･^=)',
            self::Legend => '~(°▽°)~',
        };
    }
}
