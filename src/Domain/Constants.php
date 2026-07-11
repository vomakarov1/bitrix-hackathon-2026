<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Domain;

/**
 * Контракт блока A (значения по DECOMPOSITION). Создан блоком B для автономной
 * сборки/тестов по §2.3 SDD-B; на интеграции владелец — A.
 */
final class Constants
{
    public const int INITIAL_SATIETY = 50;
    public const int FEED_PER_SESSION = 15;
    public const int STARVE_STEP = 10;
    public const int MAX_SATIETY = 100;
    public const int STARVE_TIMEOUT_HOURS = 12;
    public const int SESSION_TTL_HOURS = 24;

    public const int STAGE_HATCHLING_AT = 3;
    public const int STAGE_BEAST_AT = 7;
    public const int STAGE_LEGEND_AT = 14;

    public const int STREAK_FEED_STEP = 2;
    public const int STREAK_BONUS_CAP = 10;

    // Порог личного рекорда стрика (✨) в карточке/списке — §4.5 SDD-B, NIT ревью B4.
    public const int STREAK_RECORD_MIN_DAYS = 3;

    // Пороги настроения по satiety() — §4.5 SDD-B.
    public const int MOOD_HAPPY_AT = 70;
    public const int MOOD_OK_AT = 40;
    public const int MOOD_HUNGRY_AT = 15;

    // Разумный дефолт: уровень растёт на 1 за каждые LEVEL_STEP использований.
    public const int LEVEL_STEP = 20;
}
