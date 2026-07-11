<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Tui\Contract;

/**
 * Contract consumed by the TUI presentation layer to enumerate known skills.
 *
 * Corresponds to domain concept K5 (Skill catalog), owned by block A/B.
 *
 * @todo TEMPORARY: удалить/заменить после реализации реальных контрактов блоков A/B (см. SDD §1.1).
 */
interface SkillCatalogInterface
{
    /** @return string[] Список bare-имён скиллов. */
    public function all(): array;
}
