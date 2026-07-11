<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Tui\Contract;

/**
 * Contract consumed by the TUI presentation layer to create/delete characters.
 *
 * Corresponds to domain concept K6 (Character service), owned by block A/B.
 *
 * @todo TEMPORARY: удалить/заменить после реализации реальных контрактов блоков A/B (см. SDD §1.1).
 */
interface CharacterServiceInterface
{
    /**
     * @throws \InvalidArgumentException при невалидном имени/скилле
     * @throws \RuntimeException         при занятом скилле
     */
    public function create(string $name, string $skill, ?string $type): CharacterView;

    public function delete(string $id): void;
}
