<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Tests;

use Vladislavmakarov\BitrixHackathon2026\Storage\Clock;

/**
 * Детерминированная тестовая реализация Clock (К7).
 * Живёт в tests/, не попадает в прод-autoload.
 */
final class FixedClock implements Clock
{
    private \DateTimeImmutable $now;

    private string $today;

    public function __construct(\DateTimeImmutable $now, ?string $today = null)
    {
        $this->now = $now;
        $this->today = $today ?? $now->format('Y-m-d');
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function today(): string
    {
        return $this->today;
    }

    public function setNow(\DateTimeImmutable $now): void
    {
        $this->now = $now;
    }

    public function setToday(string $today): void
    {
        $this->today = $today;
    }
}
