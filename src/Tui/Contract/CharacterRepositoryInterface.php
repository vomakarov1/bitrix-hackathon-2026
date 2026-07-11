<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Tui\Contract;

/**
 * Contract consumed by the TUI presentation layer for reading characters.
 *
 * Corresponds to domain concept K2 (Character storage), owned by block A/B.
 *
 * @todo TEMPORARY: удалить/заменить после реализации реальных контрактов блоков A/B (см. SDD §1.1).
 */
interface CharacterRepositoryInterface
{
    /** @return CharacterView[] */
    public function all(): array;

    public function findById(string $id): ?CharacterView;
}
