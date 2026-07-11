<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Integration;

use Vladislavmakarov\BitrixHackathon2026\App\Contracts\CharacterInterface;
use Vladislavmakarov\BitrixHackathon2026\Domain\Constants;

/**
 * Чистый рендер компактной карточки питомца + подсказка (§4.5 SDD-B). Не
 * пишет в stdout сам — возвращает строку (печатает `HookHandlers` через
 * `emitCard`). Заголовок «покормлен!» — решённая семантика (§9.2 OQ-N3):
 * карточка печатается в `hook:tool-use` и отражает текущее сохранённое
 * состояние (сытость/стрик по итогу прошлого `settle`), не результат этой
 * сессии — см. оговорку в §4.5.
 */
final class PetConsoleView
{
    /** @param string $today нужен для liveStreak; передаём аргументом, чтобы класс остался чистым и тестируемым. */
    public function render(CharacterInterface $pet, string $today): string
    {
        $stage = $pet->stage();
        [$moodEmoji, $moodLabel] = PetPresentation::mood($pet->satiety());

        $lines = [
            sprintf('%s %s покормлен!', $stage->emoji(), $pet->name()),
            sprintf(
                '   сытость %d/%d  %s %s   %s',
                $pet->satiety(),
                Constants::MAX_SATIETY,
                $moodEmoji,
                $moodLabel,
                PetPresentation::streakIndicator($pet, $today)
            ),
            sprintf(
                '   стадия: %s %s   использований: %d',
                $stage->emoji(),
                $stage->label(),
                $pet->usageCount()
            ),
            '💡 Открой приложение (bin/tamagotchi), чтобы посмотреть за состоянием питомцев.',
        ];

        return implode("\n", $lines)."\n";
    }
}
