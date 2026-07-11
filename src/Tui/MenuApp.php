<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Tui;

use Symfony\Component\Tui\Terminal\Terminal;
use Symfony\Component\Tui\Tui;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\CharacterRepositoryInterface;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\CharacterServiceInterface;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\ClockInterface;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\SkillCatalogInterface;
use Vladislavmakarov\BitrixHackathon2026\Tui\Screen\CreatePetScreen;
use Vladislavmakarov\BitrixHackathon2026\Tui\Screen\DeletePetScreen;
use Vladislavmakarov\BitrixHackathon2026\Tui\Screen\MainMenuScreen;
use Vladislavmakarov\BitrixHackathon2026\Tui\Screen\PetListScreen;
use Vladislavmakarov\BitrixHackathon2026\Tui\Screen\ScreenInterface;

/**
 * Точка входа TUI-слоя. В non-TTY окружении делегирует в PlainRenderer.
 * В TTY-окружении управляет конечным автоматом экранов (Фаза 2) поверх
 * symfony/tui.
 *
 * Класс намеренно НЕ final: это единственное отступление ради тестируемости
 * (см. createTui()) — headless-тесты подсовывают Tui с VirtualTerminal через
 * анонимный/именованный подкласс, переопределяющий createTui().
 */
class MenuApp
{
    private readonly PetPresenter $presenter;
    private readonly TypeCatalog $typeCatalog;

    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly CharacterServiceInterface $characterService,
        private readonly SkillCatalogInterface $skillCatalog,
        private readonly ClockInterface $clock,
        ?PetPresenter $presenter = null,
        ?TypeCatalog $typeCatalog = null,
    ) {
        $this->presenter = $presenter ?? new PetPresenter();
        $this->typeCatalog = $typeCatalog ?? new TypeCatalog();
    }

    public function run(): int
    {
        if (!stream_isatty(\STDIN) || !stream_isatty(\STDOUT)) {
            $renderer = new PlainRenderer($this->characterRepository, $this->presenter, $this->clock);
            $renderer->render();

            return 0;
        }

        return $this->runInteractive();
    }

    /**
     * @internal Тестовый шов (единственное отступление ради тестируемости,
     * см. Verification Gate в задаче Фазы 2): в тестах переопределяется
     * анонимным подклассом, чтобы подсунуть Tui с VirtualTerminal вместо
     * реального Terminal (который переводит stdin в raw-режим и блокирует).
     */
    protected function createTui(): Tui
    {
        return new Tui(terminal: new Terminal());
    }

    private function runInteractive(): int
    {
        $tui = $this->createTui();

        if ([] === $this->characterRepository->all()) {
            $this->showCreatePetScreen($tui);
        } else {
            $this->showMainMenuScreen($tui);
        }

        $tui->run();

        return 0;
    }

    private function showScreen(Tui $tui, ScreenInterface $screen): void
    {
        $tui->clear()->add($screen->rootWidget());
        $tui->setFocus($screen->focusTarget());
        $tui->requestRender();
    }

    private function showMainMenuScreen(Tui $tui): void
    {
        $screen = new MainMenuScreen(
            onNavigate: function (string $action) use ($tui): void {
                $this->routeMainMenuAction($tui, $action);
            },
            onExit: static function () use ($tui): void {
                $tui->stop();
            },
        );

        $this->showScreen($tui, $screen);
    }

    private function routeMainMenuAction(Tui $tui, string $action): void
    {
        match ($action) {
            'list' => $this->showPetListScreen($tui),
            'create' => $this->showCreatePetScreen($tui),
            'delete' => $this->showDeletePetScreen($tui),
            'quit' => $tui->stop(),
            default => null,
        };
    }

    private function showPetListScreen(Tui $tui): void
    {
        $screen = new PetListScreen(
            $this->characterRepository,
            $this->presenter,
            $this->clock,
            onBack: function () use ($tui): void {
                $this->showMainMenuScreen($tui);
            },
        );

        if ($screen->isEmpty()) {
            // Пустой список сам не решает, куда вести — это делает MenuApp (см. BRIEF).
            $this->showCreatePetScreen($tui);

            return;
        }

        $this->showScreen($tui, $screen);
    }

    private function showCreatePetScreen(Tui $tui): void
    {
        // $screen присваивается ПОСЛЕ завершения конструктора; замыкание
        // $refresh держит ссылку на переменную, а не на ещё не существующий
        // объект — CreatePetScreen не дёргает requestRefresh() из своего
        // конструктора (см. CreatePetScreen::$constructed).
        $screen = null;
        $refresh = function () use ($tui, &$screen): void {
            $this->showScreen($tui, $screen);
        };

        $screen = new CreatePetScreen(
            $this->skillCatalog,
            $this->characterRepository,
            $this->characterService,
            $this->typeCatalog,
            onDone: function () use ($tui): void {
                $this->showPetListScreen($tui);
            },
            onCancel: function () use ($tui): void {
                $this->showMainMenuScreen($tui);
            },
            requestRefresh: $refresh,
        );

        $this->showScreen($tui, $screen);
    }

    private function showDeletePetScreen(Tui $tui): void
    {
        $screen = null;
        $refresh = function () use ($tui, &$screen): void {
            $this->showScreen($tui, $screen);
        };

        $screen = new DeletePetScreen(
            $this->characterRepository,
            $this->characterService,
            $this->presenter,
            $this->clock,
            onDone: function () use ($tui): void {
                $this->showMainMenuScreen($tui);
            },
            onCancel: function () use ($tui): void {
                $this->showMainMenuScreen($tui);
            },
            requestRefresh: $refresh,
        );

        $this->showScreen($tui, $screen);
    }
}
