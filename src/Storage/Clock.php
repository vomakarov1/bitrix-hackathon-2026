<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Storage;

/**
 * Абстракция над источником времени (К7).
 * SystemClock — прод-реализация (системные часы);
 * FixedClock (tests/) — детерминированная реализация для тестов.
 */
interface Clock
{
    /** Текущий момент. */
    public function now(): \DateTimeImmutable;

    /** Календарная дата в формате 'YYYY-MM-DD'. */
    public function today(): string;
}
