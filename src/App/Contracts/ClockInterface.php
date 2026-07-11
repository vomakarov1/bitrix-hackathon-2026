<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\App\Contracts;

/**
 * Контракт К7 (владелец — блок A). Заведён блоком B для автономной сборки/тестов
 * по §2.3 SDD-B; на интеграции — либо A реализует этот интерфейс, либо тайп-хинты
 * B меняются на конкретный класс A.
 */
interface ClockInterface
{
    public function now(): \DateTimeImmutable;

    /** Формат `YYYY-MM-DD`. */
    public function today(): string;
}
