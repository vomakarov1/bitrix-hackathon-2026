<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\App\Contracts;

/**
 * Контракт К2 (владелец — блок A). Заведён блоком B для автономной сборки/тестов
 * по §2.3 SDD-B; на интеграции — либо A реализует этот интерфейс, либо тайп-хинты
 * B меняются на конкретный класс A.
 */
interface CharacterRepositoryInterface
{
    public function findById(string $id): ?CharacterInterface;

    public function findBySkill(string $skill): ?CharacterInterface;

    /** @return CharacterInterface[] */
    public function all(): array;

    /** Кидает при дубле skill. */
    public function save(CharacterInterface $character): void;

    public function delete(string $id): void;

    /** Весь RMW — под одним `flock` (Р1 ADR). */
    public function mutate(callable $mutator): void;
}
