<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\App\Stub;

use Vladislavmakarov\BitrixHackathon2026\App\Contracts\ClockInterface;

/**
 * Минимальная in-memory заглушка ClockInterface (§2.3 SDD-B) — только чтобы B
 * собрался и smoke-кейсы прошли до готовности A. В прод не поставляется.
 *
 * Фиксируемый (принимает \DateTimeImmutable в конструкторе) — нужно тестам.
 */
final class FakeClock implements ClockInterface
{
    public function __construct(private readonly \DateTimeImmutable $now)
    {
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function today(): string
    {
        return $this->now->format('Y-m-d');
    }
}
