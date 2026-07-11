<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Tui\Contract;

/**
 * Contract consumed by the TUI presentation layer for the current date.
 *
 * Corresponds to domain concept K7 (Clock), owned by block A/B.
 *
 * @todo TEMPORARY: удалить/заменить после реализации реальных контрактов блоков A/B (см. SDD §1.1).
 */
interface ClockInterface
{
    /** @return string Текущая дата в формате YYYY-MM-DD. */
    public function today(): string;
}
