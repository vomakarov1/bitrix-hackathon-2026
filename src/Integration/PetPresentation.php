<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Integration;

use Vladislavmakarov\BitrixHackathon2026\App\Contracts\CharacterInterface;
use Vladislavmakarov\BitrixHackathon2026\Domain\Constants;

/**
 * Общий маппинг настроения/стрика для `PetConsoleView` (карточка, §4.5 SDD-B)
 * и `PetListView` (список, §4.8 SDD-B). Вынесен в отдельный класс, чтобы
 * пороги настроения и признак личного рекорда не разъезжались между двумя
 * рендерами (см. §9.2 OQ-N4 — единый формат для CLI/non-TTY).
 */
final class PetPresentation
{
    /**
     * Настроение маппится по `satiety()` (форма `mood()` у домена A ещё не
     * зафиксирована — §9.1 C3), пороги — из `Constants::MOOD_*`.
     *
     * @return array{0:string,1:string} [эмодзи, лейбл]
     */
    public static function mood(int $satiety): array
    {
        return match (true) {
            $satiety >= Constants::MOOD_HAPPY_AT => ['😋', 'сыт'],
            $satiety >= Constants::MOOD_OK_AT => ['🙂', 'норм'],
            $satiety >= Constants::MOOD_HUNGRY_AT => ['😟', 'голоден'],
            default => ['😫', 'очень голоден'],
        };
    }

    /**
     * `0` → стрик оборван; `>=1` → 🔥 {n} дн.; личный рекорд (`streak() ==
     * bestStreak()` и `liveStreak() >= STREAK_RECORD_MIN_DAYS`) добавляет ✨
     * (условие BRIEF, §4.5).
     */
    public static function streakIndicator(CharacterInterface $pet, string $today): string
    {
        $live = $pet->liveStreak($today);
        if ($live <= 0) {
            return '💤 стрик оборван';
        }

        $indicator = sprintf('🔥 %d дн.', $live);
        if ($pet->streak() === $pet->bestStreak() && $live >= Constants::STREAK_RECORD_MIN_DAYS) {
            $indicator .= ' ✨';
        }

        return $indicator;
    }
}
