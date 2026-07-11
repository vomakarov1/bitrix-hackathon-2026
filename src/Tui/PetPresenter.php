<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Tui;

use Symfony\Component\Tui\Widget\Util\StringUtils;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\CharacterView;

/**
 * Чистое форматирование данных персонажа для отображения. Без I/O и без
 * зависимости от конкретного рендерера (symfony/tui или plain-текст).
 */
final class PetPresenter
{
    public function mood(CharacterView $character): string
    {
        $satiety = $character->satiety();

        return match (true) {
            $satiety >= 70 => '😋 сыт',
            $satiety >= 40 => '🙂 норм',
            $satiety >= 15 => '😟 голоден',
            default => '😫 очень голоден',
        };
    }

    public function streakIndicator(CharacterView $character, string $today): string
    {
        $live = $character->liveStreak($today);

        if (0 === $live) {
            return '💤 стрик оборван';
        }

        if ($character->streak() === $character->bestStreak() && $live >= 3) {
            return '✨ личный рекорд!';
        }

        return sprintf('🔥 %d дн.', $live);
    }

    public function stage(CharacterView $character): string
    {
        return sprintf('%s %s', $character->stage()->emoji(), $character->stage()->label());
    }

    public function card(CharacterView $character, string $today): string
    {
        $name = StringUtils::stripControlBytes($character->name());

        $lines = [
            sprintf('%s %s', $character->stage()->emoji(), $name),
            sprintf(
                '   сытость %d/100  %s   %s',
                $character->satiety(),
                $this->mood($character),
                $this->streakIndicator($character, $today),
            ),
            sprintf(
                '   стадия: %s   использований: %d   уровень: %d',
                $this->stage($character),
                $character->usageCount(),
                $character->level(),
            ),
        ];

        return implode("\n", $lines);
    }
}
