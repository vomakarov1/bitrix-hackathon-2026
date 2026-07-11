<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Tui;

/**
 * MVP-заглушка каталога типов персонажей (см. BRIEF, открытый вопрос №2:
 * финальный список типов и его источник — домен vs конфиг — пока не решены).
 * Список статический и расширяемый по мере уточнения требований.
 */
final class TypeCatalog
{
    /** @return string[] */
    public function all(): array
    {
        return ['котик', 'дракончик', 'слайм', 'робот'];
    }
}
