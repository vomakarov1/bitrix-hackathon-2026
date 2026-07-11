<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Tui\Contract\Fake;

use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\ClockInterface;

/**
 * Фиксированная фейковая реализация ClockInterface для автономного
 * запуска/тестов TUI.
 *
 * @todo TEMPORARY: удалить/заменить после реализации реальных контрактов блоков A/B (см. SDD §1.1).
 */
final class FixedClock implements ClockInterface
{
    public function __construct(private readonly string $today)
    {
    }

    public function today(): string
    {
        return $this->today;
    }
}
