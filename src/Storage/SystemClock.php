<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Storage;

/**
 * Прод-реализация Clock (К7) — использует системные часы машины.
 */
final class SystemClock implements Clock
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
