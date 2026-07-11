<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Storage;

use Vladislavmakarov\BitrixHackathon2026\App\Contracts\ClockInterface as AppClockInterface;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\ClockInterface as TuiClockInterface;

/**
 * Прод-реализация Clock (К7) — использует системные часы машины.
 */
final class SystemClock implements Clock, AppClockInterface, TuiClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now');
    }

    public function today(): string
    {
        return $this->now()->format('Y-m-d');
    }
}
