<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\App\Contracts;

/**
 * Контракт К4 (владелец — блок A). Заведён блоком B для автономной сборки/тестов
 * по §2.3 SDD-B; на интеграции — либо A реализует этот интерфейс, либо тайп-хинты
 * B меняются на конкретный класс A.
 */
interface PathsInterface
{
    public function claudeConfigDir(): string;

    public function dataDir(): string;

    public function settingsPath(): string;
}
