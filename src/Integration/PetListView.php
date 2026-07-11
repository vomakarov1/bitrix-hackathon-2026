<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Integration;

use Vladislavmakarov\BitrixHackathon2026\App\Contracts\CharacterInterface;
use Vladislavmakarov\BitrixHackathon2026\Domain\Constants;

/**
 * Компактный однострочный рендер списка питомцев (§4.8 SDD-B) — отдельно от
 * полной карточки `PetConsoleView` (та — на одного питомца в хуке). Реализован
 * как самостоятельный класс, а не метод роутера, чтобы non-TTY fallback TUI
 * (блок C) мог переиспользовать тот же формат без дублирования (§9.2 OQ-N4).
 * Настроение/стрик берёт из `PetPresentation`, чтобы пороги совпадали с
 * карточкой.
 */
final class PetListView
{
    /**
     * @param CharacterInterface[] $pets
     *
     * Пустой список — забота вызывающей стороны (дружелюбное сообщение вне
     * этого класса, §4.8); здесь — просто пустая строка.
     */
    public function renderList(array $pets, string $today): string
    {
        $lines = [];
        foreach ($pets as $pet) {
            $lines[] = $this->renderLine($pet, $today);
        }

        return $lines === [] ? '' : implode("\n", $lines)."\n";
    }

    private function renderLine(CharacterInterface $pet, string $today): string
    {
        $stage = $pet->stage();
        [$moodEmoji] = PetPresentation::mood($pet->satiety());

        return sprintf(
            '%s %s · %d/%d %s · %s · id:%s',
            $stage->emoji(),
            $pet->name(),
            $pet->satiety(),
            Constants::MAX_SATIETY,
            $moodEmoji,
            PetPresentation::streakIndicator($pet, $today),
            $pet->id()
        );
    }
}
