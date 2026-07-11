<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Tui\Screen;

use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * Контракт "экрана" интерактивного TUI-меню (Фаза 2).
 *
 * "Экран" — не понятие symfony/tui, а концепт этого приложения: объект,
 * который умеет построить своё поддерево виджетов и сказать, какой виджет
 * должен получить фокус. Навигация между экранами (MainMenu/PetList/
 * CreatePet/DeletePet) реализована в MenuApp через колбэки, переданные
 * каждому экрану в конструктор.
 */
interface ScreenInterface
{
    /** Корневой виджет экрана, добавляемый в Tui через clear()->add(). */
    public function rootWidget(): AbstractWidget;

    /** Виджет, который нужно сфокусировать при показе экрана (может отсутствовать). */
    public function focusTarget(): ?AbstractWidget;
}
