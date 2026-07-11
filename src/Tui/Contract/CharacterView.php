<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Tui\Contract;

/**
 * Contract consumed by the TUI presentation layer, mirroring domain
 * concept K1 (Character), owned by block A.
 *
 * @todo TEMPORARY: удалить/заменить после реализации реальных контрактов блоков A/B (см. SDD §1.1).
 */
interface CharacterView
{
    public function id(): string;

    public function name(): string;

    public function skill(): string;

    public function type(): ?string;

    public function satiety(): int;

    public function usageCount(): int;

    /** Человекочитаемое настроение (доменное значение). */
    public function mood(): string;

    public function level(): int;

    public function streak(): int;

    public function bestStreak(): int;

    /** Живой стрик на дату $today (формат YYYY-MM-DD). */
    public function liveStreak(string $today): int;

    public function stage(): StageView;
}
