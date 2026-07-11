<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Domain;

use Vladislavmakarov\BitrixHackathon2026\App\Contracts\StageInterface;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\StageView;

/**
 * Стадии эволюции персонажа. Стадия — чистая функция bestStreak; эволюция необратима.
 * Пороги берутся из Constants (STAGE_HATCHLING_AT/BEAST_AT/LEGEND_AT).
 */
enum Stage implements StageInterface, StageView
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

    /** Человекочитаемая метка стадии. */
    public function label(): string
    {
        return match ($this) {
            self::Egg => 'яйцо',
            self::Hatchling => 'детёныш',
            self::Beast => 'зверь',
            self::Legend => 'легенда',
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
