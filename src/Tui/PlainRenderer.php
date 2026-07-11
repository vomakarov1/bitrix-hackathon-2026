<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Tui;

use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\CharacterRepositoryInterface;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\ClockInterface;

/**
 * Неинтерактивный fallback-рендер для non-TTY окружений (перенаправленный
 * stdout, CI, пайпы). Всегда завершается корректно, не переводит терминал
 * в raw-режим.
 */
final class PlainRenderer
{
    public function __construct(
        private readonly CharacterRepositoryInterface $repository,
        private readonly PetPresenter $presenter,
        private readonly ClockInterface $clock,
    ) {
    }

    public function render(): void
    {
        $characters = $this->repository->all();

        if ([] === $characters) {
            echo "Питомцев пока нет.\n";
            echo "Создайте первого: bin/tamagotchi create --skill=<skill> [--name=<name>] [--type=<type>]\n";
        } else {
            $today = $this->clock->today();

            foreach ($characters as $character) {
                echo $this->presenter->card($character, $today), "\n\n";
            }
        }

        echo "Интерактивное меню требует TTY; используйте CLI-команды list/create/delete.\n";
    }
}
