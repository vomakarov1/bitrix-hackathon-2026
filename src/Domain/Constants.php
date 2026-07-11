<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Domain;

/**
 * Единое место всех тюнящихся значений домена.
 * Магических чисел вне этого класса быть не должно.
 */
final class Constants
{
    /** Стартовая сытость. */
    public const int INITIAL_SATIETY = 50;

    /** Потолок сытости; пол = 0. */
    public const int MAX_SATIETY = 100;

    /** Базовая кормёжка за сессию. */
    public const int FEED_PER_SESSION = 15;

    /** Шаг голода за окно таймаута. */
    public const int STARVE_STEP = 10;

    /** Окно голодаемости, часы. */
    public const int STARVE_TIMEOUT_HOURS = 12;

    /** TTL сессии, часы. */
    public const int SESSION_TTL_HOURS = 24;

    /** Порог bestStreak для стадии 🐣 Hatchling. */
    public const int STAGE_HATCHLING_AT = 3;

    /** Порог bestStreak для стадии 🦊 Beast. */
    public const int STAGE_BEAST_AT = 7;

    /** Порог bestStreak для стадии 🐉 Legend. */
    public const int STAGE_LEGEND_AT = 14;

    /** Шаг стрик-бонуса кормёжки. */
    public const int STREAK_FEED_STEP = 2;

    /** Потолок стрик-бонуса. */
    public const int STREAK_BONUS_CAP = 10;

    /** usageCount на уровень (MVP-дефолт). */
    public const int LEVEL_STEP = 10;

    /** Порог сытости для 😋 «сыт». */
    public const int MOOD_SATISFIED_AT = 70;

    /** Порог сытости для 🙂 «норм». */
    public const int MOOD_OK_AT = 40;

    /** Порог сытости для 😟 «голоден»; ниже — 😫. */
    public const int MOOD_HUNGRY_AT = 15;
}
