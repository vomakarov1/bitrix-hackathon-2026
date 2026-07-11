<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Tui\Contract\Fake;

use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\SkillCatalogInterface;

/**
 * Статическая фейковая реализация SkillCatalogInterface для автономного
 * запуска/тестов TUI.
 *
 * @todo TEMPORARY: удалить/заменить после реализации реальных контрактов блоков A/B (см. SDD §1.1).
 */
final class StaticSkillCatalog implements SkillCatalogInterface
{
    /** @param string[] $skills */
    public function __construct(
        private readonly array $skills = ['develop', 'code-review', 'sdd', 'run'],
    ) {
    }

    /** @return string[] */
    public function all(): array
    {
        return $this->skills;
    }
}
