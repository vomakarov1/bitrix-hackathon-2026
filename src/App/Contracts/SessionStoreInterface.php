<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\App\Contracts;

/**
 * Контракт К3 (владелец — блок A). Заведён блоком B для автономной сборки/тестов
 * по §2.3 SDD-B; на интеграции — либо A реализует этот интерфейс, либо тайп-хинты
 * B меняются на конкретный класс A.
 */
interface SessionStoreInterface
{
    public function markSkillUsed(string $sessionId, string $skill, \DateTimeImmutable $now): void;

    /** @return string[] */
    public function getUsedSkills(string $sessionId): array;

    public function clear(string $sessionId): void;

    public function pruneExpired(int $ttlHours, \DateTimeImmutable $now): void;
}
